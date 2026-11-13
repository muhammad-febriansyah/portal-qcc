<?php

namespace App\Services\Teams\Matching;

use App\Services\Ai\AiConnection;
use App\Services\Ai\Exceptions\AiUnavailable;
use App\Services\Ai\Exceptions\InvalidAiOutput;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Responses API with a strict JSON schema. Backend only: the key never
 * leaves the server and errors never include the raw response body.
 */
class OpenAiTeamMatchingClient implements TeamMatchingClient
{
    public function __construct(private readonly AiConnection $connection) {}

    public function suggest(array $request): array
    {
        $credentials = $this->connection->credentials();

        try {
            $httpRequest = Http::withToken($credentials['key'])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(max(5, min(60, (int) config('team_matching.request_timeout_seconds', 45))));

            $body = [
                'model' => $credentials['model'],
                'store' => false,
                'input' => [
                    ['role' => 'system', 'content' => $request['system']],
                    ['role' => 'user', 'content' => json_encode($request['input'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'team_suggestion',
                        'strict' => true,
                        'schema' => $request['schema'],
                    ],
                ],
            ];

            $effort = $this->reasoningEffort($credentials['model']);

            if ($effort !== null) {
                $body['reasoning'] = ['effort' => $effort];
            }

            $response = $httpRequest->post(
                rtrim((string) config('scoring.openai.base_url'), '/').'/responses',
                $body,
            );
        } catch (ConnectionException) {
            throw new AiUnavailable('Layanan AI tidak merespons tepat waktu.', true, 'ai_timeout');
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new AiUnavailable('Layanan AI sedang sibuk atau bermasalah.', true, 'ai_busy');
        }

        if ($response->failed()) {
            throw new AiUnavailable('Permintaan ke layanan AI ditolak.', false, 'ai_rejected');
        }

        return [
            'payload' => $this->extractPayload($response),
            'usage' => (array) $response->json('usage', []),
            'model' => (string) ($response->json('model') ?? $credentials['model']),
        ];
    }

    /**
     * Picking a team from a short, ranked list needs little reasoning, so each model gets the
     * lightest effort it accepts: "none" for GPT-5.1 and later, "minimal" for GPT-5, mini and
     * nano, "low" for the o-series. Models without the setting get none.
     */
    private function reasoningEffort(string $model): ?string
    {
        if (str_starts_with($model, 'gpt-5-pro') || str_contains($model, '-chat')) {
            return null;
        }

        return match (true) {
            preg_match('/^gpt-5\.\d/', $model) === 1 => 'none',
            preg_match('/^gpt-5(?:-|$)/', $model) === 1 => 'minimal',
            preg_match('/^o[134](?:-|$)/', $model) === 1 => 'low',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(Response $response): array
    {
        $text = null;

        foreach ((array) $response->json('output', []) as $item) {
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new InvalidAiOutput(['Model menolak permintaan ini.']);
                }

                if (($content['type'] ?? null) === 'output_text') {
                    $text = $content['text'] ?? null;
                }
            }
        }

        $decoded = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($decoded)) {
            throw new InvalidAiOutput(['Respons AI bukan JSON yang valid.']);
        }

        return $decoded;
    }
}
