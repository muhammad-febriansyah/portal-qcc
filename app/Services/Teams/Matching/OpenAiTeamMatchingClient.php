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

            if ($this->supportsReasoningEffort($credentials['model'])) {
                $body['reasoning'] = ['effort' => 'low'];
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

    private function supportsReasoningEffort(string $model): bool
    {
        return preg_match('/^(?:gpt-5(?:[.-]|$)|o[134](?:-|$))/', $model) === 1
            && ! str_starts_with($model, 'gpt-5-pro');
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
