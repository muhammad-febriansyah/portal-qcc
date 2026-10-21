<?php

use App\Services\Scoring\Exceptions\InvalidScoringResult;
use App\Services\Scoring\Exceptions\ScoringUnavailable;
use App\Services\Scoring\OpenAiScoringClient;
use App\Services\Scoring\ScoringRequestBuilder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    config([
        'scoring.openai.key' => 'sk-test-secret',
        'scoring.openai.model' => 'test-model',
        'scoring.openai.base_url' => 'https://api.openai.test/v1',
    ]);
});

function builtRequest(): array
{
    return (new ScoringRequestBuilder)->build(scoringRubric(), oneEvidence());
}

function openAiAnswer(array $payload): array
{
    return [
        'model' => 'test-model-2026',
        'usage' => ['input_tokens' => 120, 'output_tokens' => 80],
        'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($payload)]]]],
    ];
}

test('a successful answer returns the decoded payload, usage and model', function () {
    Http::fake(['api.openai.test/*' => Http::response(openAiAnswer(['overall_summary' => 'ok']))]);

    $answer = (new OpenAiScoringClient)->score(builtRequest());

    expect($answer['payload'])->toBe(['overall_summary' => 'ok'])
        ->and($answer['usage'])->toBe(['input_tokens' => 120, 'output_tokens' => 80])
        ->and($answer['model'])->toBe('test-model-2026');
});

test('the request is sent from the backend with the key, a strict schema and no storage', function () {
    Http::fake(['api.openai.test/*' => Http::response(openAiAnswer([]))]);

    (new OpenAiScoringClient)->score(builtRequest());

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://api.openai.test/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer sk-test-secret')
            && $body['model'] === 'test-model'
            && $body['store'] === false
            && $body['text']['format']['type'] === 'json_schema'
            && $body['text']['format']['strict'] === true
            && $body['input'][0]['role'] === 'system';
    });
});

test('a missing key or model is a non retryable configuration error', function () {
    config(['scoring.openai.key' => null]);

    try {
        (new OpenAiScoringClient)->score(builtRequest());
    } catch (ScoringUnavailable $e) {
        expect($e->retryable)->toBeFalse()->and($e->errorCode)->toBe('ai_not_configured');

        return;
    }

    throw new RuntimeException('Expected ScoringUnavailable');
});

test('rate limits and server errors are retryable', function (int $status) {
    Http::fake(['api.openai.test/*' => Http::response(['error' => 'x'], $status)]);

    try {
        (new OpenAiScoringClient)->score(builtRequest());
    } catch (ScoringUnavailable $e) {
        expect($e->retryable)->toBeTrue()->and($e->errorCode)->toBe('ai_busy');

        return;
    }

    throw new RuntimeException('Expected ScoringUnavailable');
})->with([429, 500, 503]);

test('a rejected request is not retryable and never leaks the response body', function () {
    Http::fake(['api.openai.test/*' => Http::response(['error' => ['message' => 'invalid key sk-test-secret']], 401)]);

    try {
        (new OpenAiScoringClient)->score(builtRequest());
    } catch (ScoringUnavailable $e) {
        expect($e->retryable)->toBeFalse()
            ->and($e->errorCode)->toBe('ai_rejected')
            ->and($e->getMessage())->not->toContain('sk-test-secret');

        return;
    }

    throw new RuntimeException('Expected ScoringUnavailable');
});

test('a timeout is retryable', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    try {
        (new OpenAiScoringClient)->score(builtRequest());
    } catch (ScoringUnavailable $e) {
        expect($e->retryable)->toBeTrue()->and($e->errorCode)->toBe('ai_timeout');

        return;
    }

    throw new RuntimeException('Expected ScoringUnavailable');
});

test('an answer that is not json is an invalid result', function () {
    Http::fake(['api.openai.test/*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'bukan json']]]]])]);

    expect(fn () => (new OpenAiScoringClient)->score(builtRequest()))->toThrow(InvalidScoringResult::class, 'bukan JSON');
});

test('a model refusal is an invalid result', function () {
    Http::fake(['api.openai.test/*' => Http::response(['output' => [['content' => [['type' => 'refusal', 'refusal' => 'tidak bisa']]]]])]);

    expect(fn () => (new OpenAiScoringClient)->score(builtRequest()))->toThrow(InvalidScoringResult::class, 'menolak');
});
