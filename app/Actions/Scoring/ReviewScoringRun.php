<?php

namespace App\Actions\Scoring;

use App\Enums\ScoringStatus;
use App\Models\ScoringRun;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ProjectNotifier;
use App\Services\Scoring\WeightedScoringCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The human decision on an AI score. Nothing is approved automatically.
 */
class ReviewScoringRun
{
    public function __construct(
        private readonly WeightedScoringCalculator $calculator,
        private readonly AuditService $audit,
        private readonly ProjectNotifier $notifier,
    ) {}

    /**
     * Approve the result, optionally with reviewer scores that replace the AI scores.
     *
     * @param  array<int, float|int|string>  $reviewerScores  keyed by rubric criterion id
     */
    public function approve(ScoringRun $run, User $reviewer, array $reviewerScores = [], ?string $reason = null, ?string $comment = null): ScoringRun
    {
        Gate::forUser($reviewer)->authorize('approve', $run);

        return DB::transaction(function () use ($run, $reviewer, $reviewerScores, $reason, $comment): ScoringRun {
            $locked = $this->lockPendingRun($run);
            $result = $locked->result()->with('criterionScores.criterion')->firstOrFail();
            $criteria = $locked->rubricVersion->criteria;
            $rows = $result->criterionScores->keyBy('rubric_criterion_id');

            foreach (array_keys($reviewerScores) as $criterionId) {
                if (! $rows->has($criterionId)) {
                    throw ValidationException::withMessages(['scores' => 'Kriteria tidak termasuk dalam penilaian ini.']);
                }
            }

            $changed = [];
            $effective = [];

            foreach ($rows as $criterionId => $row) {
                $ai = $this->hundredths($row->ai_score);
                $given = array_key_exists($criterionId, $reviewerScores) ? $this->hundredths($reviewerScores[$criterionId]) : $ai;

                if ($given < 0 || $given > 10000) {
                    throw ValidationException::withMessages(['scores' => 'Nilai harus antara 0 dan 100.']);
                }

                $effective[$criterionId] = $given / 100;

                if ($given !== $ai) {
                    $changed[$criterionId] = $given / 100;
                }
            }

            if ($changed !== [] && ($reason === null || trim($reason) === '')) {
                throw ValidationException::withMessages(['reason' => 'Alasan wajib diisi bila nilai AI diubah.']);
            }

            $before = [];
            $after = [];

            foreach ($changed as $criterionId => $score) {
                $row = $rows[$criterionId];
                $before[$row->criterion->code] = $row->ai_score;
                $after[$row->criterion->code] = number_format($score, 2, '.', '');
                $row->forceFill(['reviewer_score' => $score, 'override_reason' => $reason])->save();
            }

            foreach ($rows as $criterionId => $row) {
                $row->forceFill(['final_score' => $effective[$criterionId]])->save();
            }

            $weighted = $this->calculator->calculate($criteria, $effective);

            $result->forceFill([
                'reviewer_weighted_score' => $changed === [] ? null : $weighted,
                'final_score' => $weighted,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_comment' => $comment,
            ])->save();

            $locked->forceFill(['status' => ScoringStatus::Approved, 'lock_version' => $locked->lock_version + 1])->save();

            if ($changed !== []) {
                $this->audit->record('scoring.overridden', $locked, before: $before, after: $after, reason: $reason, subjectLabel: $this->label($locked));
            }

            $this->audit->record(
                'scoring.approved',
                $locked,
                before: ['ai_weighted_score' => $result->ai_weighted_score],
                after: ['final_score' => $weighted],
                reason: $comment,
                subjectLabel: $this->label($locked),
            );
            $this->notifier->notify(
                $this->notifier->scoringReviewers($locked->project),
                $locked->project,
                'AI Scoring disetujui',
                "Hasil scoring proyek {$locked->project->title} disetujui.",
                route('admin.scoring.show', $locked, false),
                'SCORING_APPROVED',
                $reviewer,
            );

            return $locked->refresh();
        });
    }

    public function requestRevision(ScoringRun $run, User $reviewer, string $reason): ScoringRun
    {
        Gate::forUser($reviewer)->authorize('review', $run);

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan wajib diisi saat meminta revisi.']);
        }

        return DB::transaction(function () use ($run, $reviewer, $reason): ScoringRun {
            $locked = $this->lockPendingRun($run);

            $locked->result()->firstOrFail()->forceFill([
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_comment' => $reason,
            ])->save();

            $locked->forceFill(['status' => ScoringStatus::RevisionRequired, 'lock_version' => $locked->lock_version + 1])->save();

            $this->audit->record('scoring.revision_requested', $locked, reason: $reason, subjectLabel: $this->label($locked));
            $this->notifier->notify(
                $this->notifier->scoringReviewers($locked->project),
                $locked->project,
                'AI Scoring perlu revisi',
                "Hasil scoring proyek {$locked->project->title} perlu diperbaiki: {$reason}",
                route('admin.scoring.show', $locked, false),
                'SCORING_REVISION',
                $reviewer,
            );

            return $locked->refresh();
        });
    }

    /**
     * Lock the row so two reviewers acting at once end with exactly one decision.
     */
    private function lockPendingRun(ScoringRun $run): ScoringRun
    {
        $locked = ScoringRun::query()->with(['rubricVersion.criteria', 'project'])->whereKey($run->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== ScoringStatus::PendingReview) {
            throw ValidationException::withMessages(['status' => 'Penilaian ini sudah diproses atau belum siap direview.']);
        }

        return $locked;
    }

    private function hundredths(float|int|string $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function label(ScoringRun $run): string
    {
        return "Penilaian proyek #{$run->project_id}";
    }
}
