<?php

namespace App\Jobs;

use App\Enums\ScoringStatus;
use App\Models\ScoringResult;
use App\Models\ScoringRun;
use App\Services\AuditService;
use App\Services\Scoring\Contracts\EvidenceResolver;
use App\Services\Scoring\Contracts\ScoringModelClient;
use App\Services\Scoring\Exceptions\InvalidScoringResult;
use App\Services\Scoring\Exceptions\ScoringUnavailable;
use App\Services\Scoring\ScoringRequestBuilder;
use App\Services\Scoring\ScoringResultValidator;
use App\Services\Scoring\WeightedScoringCalculator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunAiScoring implements ShouldBeUnique, ShouldQueue
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
        EvidenceResolver $evidence,
        ScoringRequestBuilder $builder,
        ScoringModelClient $client,
        ScoringResultValidator $validator,
        WeightedScoringCalculator $calculator,
        AuditService $audit,
    ): void {
        $run = ScoringRun::query()->with('rubricVersion.criteria')->find($this->runId);

        if ($run === null || $run->status->isFinished() || $run->status === ScoringStatus::PendingReview) {
            return;
        }

        $run->forceFill([
            'attempt_count' => $run->attempt_count + 1,
            'started_at' => $run->started_at ?? now(),
        ])->save();

        $manifest = $evidence->readyEvidenceFor($run->project_id);
        $manifestIds = array_column($manifest, 'evidence_id');

        if (count($manifest) < (int) config('scoring.minimum_evidence')) {
            $run->forceFill([
                'status' => ScoringStatus::InsufficientEvidence,
                'input_manifest_json' => ['evidence_count' => count($manifest), 'minimum' => (int) config('scoring.minimum_evidence')],
                'completed_at' => now(),
            ])->save();
            $audit->record('scoring.insufficient_evidence', $run, subjectLabel: "Penilaian proyek #{$run->project_id}");

            return;
        }

        $request = $builder->build($run->rubricVersion, $manifest);

        $run->forceFill([
            'status' => ScoringStatus::AiScoring,
            'prompt_version' => $request['prompt_version'],
            'request_hash' => $request['request_hash'],
            'input_manifest_json' => array_map(
                fn (array $item): array => ['evidence_id' => $item['evidence_id'], 'title' => $item['title'], 'sha256' => hash('sha256', $item['content'])],
                $manifest,
            ),
        ])->save();

        try {
            $answer = $client->score($request);
            $payload = $validator->validate($answer['payload'], $run->rubricVersion, $manifestIds);
        } catch (InvalidScoringResult $e) {
            $this->markFailed($run, 'invalid_output', $e->getMessage(), $audit);

            return;
        } catch (ScoringUnavailable $e) {
            if (! $e->retryable) {
                $this->markFailed($run, $e->errorCode, $e->getMessage(), $audit);

                return;
            }

            throw $e;
        }

        DB::transaction(function () use ($run, $payload, $answer, $calculator, $audit): void {
            $criteria = $run->rubricVersion->criteria->keyBy('code');
            $scores = [];

            foreach ($payload['criteria'] as $item) {
                $scores[$criteria[$item['criterion_code']]->id] = $item['score'];
            }

            $result = ScoringResult::query()->create([
                'scoring_run_id' => $run->id,
                'overall_summary' => $payload['overall_summary'],
                'cautions_json' => $payload['cautions'],
                'ai_weighted_score' => $calculator->calculate($run->rubricVersion->criteria, $scores),
            ]);

            foreach ($payload['criteria'] as $item) {
                $result->criterionScores()->create([
                    'rubric_criterion_id' => $criteria[$item['criterion_code']]->id,
                    'ai_score' => $item['score'],
                    'rationale' => $item['rationale'],
                    'gaps_json' => $item['gaps'],
                    'uncertainty' => $item['uncertainty'],
                    'evidence_references_json' => $item['evidence_references'],
                ]);
            }

            $run->forceFill([
                'status' => ScoringStatus::PendingReview,
                'model' => $answer['model'],
                'usage_json' => $answer['usage'],
                'completed_at' => now(),
                'error_code' => null,
                'error_summary' => null,
            ])->save();

            $audit->record('scoring.completed', $run, after: ['ai_weighted_score' => $result->ai_weighted_score], subjectLabel: "Penilaian proyek #{$run->project_id}");
        });
    }

    /**
     * Called by the queue once every retry is used up.
     */
    public function failed(Throwable $exception): void
    {
        $run = ScoringRun::query()->find($this->runId);

        if ($run === null || $run->status->isFinished()) {
            return;
        }

        $code = $exception instanceof ScoringUnavailable ? $exception->errorCode : 'unexpected_error';
        $summary = $exception instanceof ScoringUnavailable ? $exception->getMessage() : 'Terjadi kendala saat memproses penilaian.';

        $this->markFailed($run, $code, $summary, app(AuditService::class));
    }

    private function markFailed(ScoringRun $run, string $code, string $summary, AuditService $audit): void
    {
        $run->forceFill([
            'status' => ScoringStatus::Failed,
            'error_code' => $code,
            'error_summary' => $summary,
            'completed_at' => now(),
        ])->save();

        $audit->record('scoring.failed', $run, after: ['error_code' => $code], subjectLabel: "Penilaian proyek #{$run->project_id}");
    }
}
