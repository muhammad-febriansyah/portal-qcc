<?php

namespace App\Jobs;

use App\Enums\TeamMatchingStatus;
use App\Models\TeamMatchingRun;
use App\Services\Ai\Exceptions\AiUnavailable;
use App\Services\Ai\Exceptions\InvalidAiOutput;
use App\Services\AuditService;
use App\Services\Teams\Matching\TeamMatchingClient;
use App\Services\Teams\Matching\TeamMatchingRequestBuilder;
use App\Services\Teams\Matching\TeamMatchingResultValidator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunTeamMatching implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 180;

    public function __construct(public int $runId) {}

    public function uniqueId(): string
    {
        return (string) $this->runId;
    }

    public function handle(
        TeamMatchingRequestBuilder $builder,
        TeamMatchingClient $client,
        TeamMatchingResultValidator $validator,
        AuditService $audit,
    ): void {
        $run = TeamMatchingRun::query()->with('project')->find($this->runId);

        if ($run === null || ! $run->status->isActive()) {
            return;
        }

        $request = $builder->build($run->project, $run->candidate_snapshot_json);

        $run->forceFill([
            'status' => TeamMatchingStatus::Processing,
            'prompt_version' => $request['prompt_version'],
            'request_hash' => $request['request_hash'],
        ])->save();

        try {
            $answer = $client->suggest($request);
            $payload = $validator->validate($answer['payload'], $run->candidate_snapshot_json);
        } catch (InvalidAiOutput $e) {
            $this->markFailed($run, 'invalid_output', $audit);

            return;
        } catch (AiUnavailable $e) {
            if (! $e->retryable) {
                $this->markFailed($run, $e->errorCode, $audit);

                return;
            }

            throw $e;
        }

        $run->forceFill([
            'status' => TeamMatchingStatus::Ready,
            'output_json' => $payload,
            'model' => $answer['model'],
            'token_usage_json' => $answer['usage'],
            'validated_at' => now(),
            'error_code' => null,
        ])->save();

        $audit->record('team_matching.completed', $run, subjectLabel: $this->label($run));
    }

    /**
     * Called by the queue once every retry is used up.
     */
    public function failed(Throwable $exception): void
    {
        $run = TeamMatchingRun::query()->find($this->runId);

        if ($run === null || ! $run->status->isActive()) {
            return;
        }

        $this->markFailed($run, $exception instanceof AiUnavailable ? $exception->errorCode : 'unexpected_error', app(AuditService::class));
    }

    private function markFailed(TeamMatchingRun $run, string $code, AuditService $audit): void
    {
        $run->forceFill(['status' => TeamMatchingStatus::Failed, 'error_code' => $code])->save();

        $audit->record('team_matching.failed', $run, subjectLabel: $this->label($run));
    }

    private function label(TeamMatchingRun $run): string
    {
        return 'Proposal PRP-'.sprintf('%04d', $run->project_id);
    }
}
