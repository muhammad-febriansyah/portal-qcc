<?php

namespace App\Services\Scoring;

use App\Services\Scoring\Contracts\ScoringModelClient;
use App\Services\Scoring\Exceptions\InvalidScoringResult;
use App\Services\Scoring\Exceptions\ScoringUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Calls the OpenAI Responses API with a strict JSON schema. Backend only: the key
 * never leaves the server and error details never include the raw response body.
 */
class OpenAiScoringClient implements ScoringModelClient
{
    public function score(array $request): array
    {
        $key = config('scoring.openai.key');
        $model = config('scoring.openai.model');

        if (! is_string($key) || $key === '' || ! is_string($model) || $model === '') {
            throw new ScoringUnavailable('Penyedia AI belum dikonfigurasi.', false, 'ai_not_configured');
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout((int) config('scoring.openai.timeout'))
                ->post(rtrim((string) config('scoring.openai.base_url'), '/').'/responses', [
                    'model' => $model,
                    'store' => false,
                    'input' => [
                        ['role' => 'system', 'content' => $request['system']],
                        ['role' => 'user', 'content' => json_encode($request['input'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'scoring_result',
                            'strict' => true,
                            'schema' => $request['schema'],
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new ScoringUnavailable('Layanan AI tidak merespons tepat waktu.', true, 'ai_timeout');
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new ScoringUnavailable('Layanan AI sedang sibuk atau bermasalah.', true, 'ai_busy');
        }

        if ($response->failed()) {
            throw new ScoringUnavailable('Permintaan ke layanan AI ditolak.', false, 'ai_rejected');
        }

        return [
            'payload' => $this->extractPayload($response),
            'usage' => (array) $response->json('usage', []),
            'model' => (string) ($response->json('model') ?? $model),
        ];
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
                    throw new InvalidScoringResult(['Model menolak menilai permintaan ini.']);
                }

                if (($content['type'] ?? null) === 'output_text') {
                    $text = $content['text'] ?? null;
                }
            }
        }

        $decoded = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($decoded)) {
            throw new InvalidScoringResult(['Respons AI bukan JSON yang valid.']);
        }

        return $decoded;
    }
}
