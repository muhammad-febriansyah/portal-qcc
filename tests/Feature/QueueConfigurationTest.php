<?php

use App\Jobs\RunAiScoring;
use App\Jobs\RunTeamMatching;

it('keeps the redis retry_after longer than the timeout of every AI job', function () {
    // A shorter retry_after makes Redis hand a job to a second worker while the first still runs it.
    $longestJobTimeout = max((new RunAiScoring(1))->timeout, (new RunTeamMatching(1))->timeout);

    expect((int) config('queue.connections.redis.retry_after'))->toBeGreaterThan($longestJobTimeout);
});

it('sends AI team matching and AI scoring to the ai queue by default', function () {
    expect(config('scoring.queue'))->toBe('ai')
        ->and(config('team_matching.queue'))->toBe('ai');
});

it('keeps team matching retries bounded for interactive wait times', function () {
    $job = new RunTeamMatching(1);

    expect($job->tries)->toBe(2)
        ->and($job->backoff)->toBe([5])
        ->and($job->timeout)->toBe(75)
        ->and((int) config('team_matching.request_timeout_seconds'))->toBeLessThanOrEqual(60);
});

it('makes the development queue listener take the ai queue as well as the default queue', function () {
    $this->artisan('dev:list')
        ->expectsOutputToContain('queue:listen --queue=ai,default')
        ->assertSuccessful();
});
