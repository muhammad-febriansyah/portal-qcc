<?php

namespace App\Actions\Proposals;

use App\Enums\ProposalStatus;
use App\Enums\StepStatus;
use App\Models\ProjectStep;
use App\Models\ProjectStepHistory;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The life of one QCC step: NOT_STARTED, IN_PROGRESS, SUBMITTED, UNDER_REVIEW,
 * APPROVED or REVISION_REQUIRED. Every move runs in a transaction with the step
 * row locked, checks the current state, bumps the version and leaves a history
 * row and an audit entry, so two people acting at once end in exactly one state.
 */
class ProjectStepWorkflow
{
    /** @var list<string> */
    public const CONTENT_FIELDS = ['activity_description', 'plan_text', 'actual_text', 'target_date', 'progress_pct', 'submitter_note'];

    private const EDITABLE = [StepStatus::NotStarted, StepStatus::InProgress, StepStatus::RevisionRequired];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(ProjectStep $step, User $actor, array $data, int $lockVersion): ProjectStep
    {
        return $this->run($step, function (ProjectStep $step) use ($actor, $data, $lockVersion): ProjectStep {
            $this->guardEditable($step);
            $this->guardVersion($step, $lockVersion);

            $before = $this->content($step);
            $this->applyContent($step, $data);
            $newStatus = StepStatus::InProgress;

            if ($this->content($step) === $before && $step->status === $newStatus) {
                return $step;
            }

            $this->move($step, $actor, 'SAVED', $newStatus);

            return $step;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(ProjectStep $step, User $actor, array $data, int $lockVersion): ProjectStep
    {
        return $this->run($step, function (ProjectStep $step) use ($actor, $data, $lockVersion): ProjectStep {
            $this->guardEditable($step);
            $this->guardVersion($step, $lockVersion);
            $this->applyContent($step, $data);

            $missing = [];

            if (blank($step->activity_description)) {
                $missing['activity_description'] = 'Deskripsi aktivitas wajib diisi sebelum step diajukan.';
            }

            if (blank($step->plan_text)) {
                $missing['plan_text'] = 'Rencana wajib diisi sebelum step diajukan.';
            }

            if ($missing !== []) {
                throw ValidationException::withMessages($missing);
            }

            if (config('qcc.steps.require_sequence') && $step->step_number > 1) {
                $previous = ProjectStep::query()
                    ->where('project_id', $step->project_id)
                    ->where('step_number', $step->step_number - 1)
                    ->first();

                if ($previous?->status !== StepStatus::Approved) {
                    throw ValidationException::withMessages(['step' => 'Step sebelumnya harus disetujui lebih dulu.']);
                }
            }

            $step->forceFill(['submitted_at' => now(), 'approved_at' => null]);
            $this->move($step, $actor, 'SUBMITTED', StepStatus::Submitted);

            return $step;
        });
    }

    public function startReview(ProjectStep $step, User $actor): ProjectStep
    {
        return $this->run($step, function (ProjectStep $step) use ($actor): ProjectStep {
            $this->guardStatus($step, [StepStatus::Submitted], 'Hanya step yang diajukan yang bisa mulai direview.');
            $this->move($step, $actor, 'REVIEW_STARTED', StepStatus::UnderReview);

            return $step;
        });
    }

    public function approve(ProjectStep $step, User $actor): ProjectStep
    {
        return $this->run($step, function (ProjectStep $step) use ($actor): ProjectStep {
            $this->guardStatus($step, [StepStatus::UnderReview], 'Persetujuan hanya bisa diberikan saat step sedang direview.');
            $step->forceFill(['progress_pct' => 100, 'approved_at' => now()]);
            $this->move($step, $actor, 'APPROVED', StepStatus::Approved);

            return $step;
        });
    }

    public function requestRevision(ProjectStep $step, User $actor, ?string $reason): ProjectStep
    {
        return $this->run($step, function (ProjectStep $step) use ($actor, $reason): ProjectStep {
            $this->guardStatus($step, [StepStatus::UnderReview], 'Revisi hanya bisa diminta saat step sedang direview.');

            if (blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Alasan revisi wajib diisi.']);
            }

            $this->move($step, $actor, 'REVISION_REQUESTED', StepStatus::RevisionRequired, $reason);

            return $step;
        });
    }

    /**
     * @param  callable(ProjectStep): ProjectStep  $callback
     */
    private function run(ProjectStep $step, callable $callback): ProjectStep
    {
        return DB::transaction(function () use ($step, $callback): ProjectStep {
            $locked = ProjectStep::query()->with('project')->lockForUpdate()->findOrFail($step->id);

            if ($locked->project->proposal_status !== ProposalStatus::Approved) {
                throw ValidationException::withMessages(['step' => 'Step hanya bisa dikerjakan setelah proposal disetujui.']);
            }

            return $callback($locked);
        });
    }

    private function guardEditable(ProjectStep $step): void
    {
        $this->guardStatus($step, self::EDITABLE, 'Step yang sedang diajukan, direview, atau sudah disetujui tidak bisa diubah.');
    }

    /**
     * @param  list<StepStatus>  $allowed
     */
    private function guardStatus(ProjectStep $step, array $allowed, string $message): void
    {
        if (! in_array($step->status, $allowed, true)) {
            throw ValidationException::withMessages(['step' => $message]);
        }
    }

    private function guardVersion(ProjectStep $step, int $lockVersion): void
    {
        if ($step->lock_version !== $lockVersion) {
            throw ValidationException::withMessages(['lock_version' => 'Step ini baru saja diubah orang lain. Muat ulang halaman, lalu coba lagi.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyContent(ProjectStep $step, array $data): void
    {
        foreach (self::CONTENT_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                $step->{$field} = is_string($value) && trim($value) === '' ? null : $value;
            }
        }

        $step->progress_pct = (int) ($step->progress_pct ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(ProjectStep $step): array
    {
        return [
            'activity_description' => $step->activity_description,
            'plan_text' => $step->plan_text,
            'actual_text' => $step->actual_text,
            'target_date' => $step->target_date?->toDateString(),
            'progress_pct' => $step->progress_pct,
            'submitter_note' => $step->submitter_note,
        ];
    }

    private function move(ProjectStep $step, User $actor, string $action, StepStatus $to, ?string $reason = null): void
    {
        $from = $step->status;

        $step->forceFill([
            'status' => $to,
            'lock_version' => $step->lock_version + 1,
        ])->save();

        ProjectStepHistory::query()->create([
            'qcc_step_id' => $step->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'previous_status' => $from->value,
            'new_status' => $to->value,
            'version' => $step->lock_version,
            'snapshot_json' => $this->content($step),
            'reason' => $reason,
            'created_at' => now(),
        ]);

        $this->audit->record(
            'step.'.strtolower($action),
            $step,
            before: ['status' => $from->value],
            after: ['status' => $to->value, 'version' => $step->lock_version],
            reason: $reason,
            subjectLabel: 'Step '.$step->step_number.' ('.$step->definition()->label().') proposal PRP-'.sprintf('%04d', $step->project_id),
        );
    }
}
