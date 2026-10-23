<?php

namespace App\Actions\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\ReviewDecision;
use App\Models\User;
use App\Notifications\ProposalWorkflowNotification;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewProposal
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly InitializeProjectSteps $initializeProjectSteps,
    ) {}

    public function start(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor): void {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ($project->proposal_status !== ProposalStatus::Submitted) {
                throw ValidationException::withMessages(['proposal' => 'Hanya proposal yang diajukan dapat mulai direview.']);
            }

            $this->recordTransition($project, $actor, 'REVIEW_STARTED', ProposalStatus::UnderReview, null);
        });
    }

    public function decide(Project $project, User $actor, string $decision, ?string $reason): void
    {
        DB::transaction(function () use ($project, $actor, $decision, $reason): void {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ($project->proposal_status !== ProposalStatus::UnderReview) {
                throw ValidationException::withMessages(['proposal' => 'Keputusan hanya dapat diberikan saat proposal sedang direview.']);
            }

            [$status, $label] = match ($decision) {
                'APPROVE' => [ProposalStatus::Approved, 'Proposal disetujui'],
                'REQUEST_REVISION' => [ProposalStatus::RevisionRequired, 'Proposal perlu direvisi'],
                'REJECT' => [ProposalStatus::Rejected, 'Proposal ditolak'],
                default => throw ValidationException::withMessages(['decision' => 'Keputusan tidak valid.']),
            };

            if (in_array($decision, ['REQUEST_REVISION', 'REJECT'], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Alasan wajib diisi untuk revisi atau penolakan.']);
            }

            $this->recordTransition($project, $actor, $decision, $status, $reason);

            if (ProposalWorkflowNotification::enabled()) {
                DB::afterCommit(function () use ($project, $label, $decision, $reason): void {
                    $project->proposer?->notify(new ProposalWorkflowNotification(
                        $project->id,
                        $label,
                        $reason ?: "Status proposal {$project->title} telah diperbarui.",
                        route('proposals.show', $project, false),
                        $decision,
                    ));
                });
            }
        });
    }

    private function recordTransition(Project $project, User $actor, string $decision, ProposalStatus $status, ?string $reason): void
    {
        $previousStatus = $project->proposal_status;
        $project->forceFill([
            'proposal_status' => $status,
            'approved_at' => $status === ProposalStatus::Approved ? now() : null,
            'lock_version' => $project->lock_version + 1,
        ])->save();

        if ($status === ProposalStatus::Approved) {
            $this->initializeProjectSteps->handle($project);
        }

        ReviewDecision::query()->create([
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'decision' => $decision,
            'previous_status' => $previousStatus->value,
            'new_status' => $status->value,
            'reason' => $reason,
            'decided_at' => now(),
        ]);

        $this->audit->record(
            'proposal.'.strtolower(str_replace('_', '.', $decision)),
            $project,
            before: ['status' => $previousStatus->value],
            after: ['status' => $status->value],
            reason: $reason,
            subjectLabel: 'Proposal PRP-'.sprintf('%04d', $project->id),
        );
    }
}
