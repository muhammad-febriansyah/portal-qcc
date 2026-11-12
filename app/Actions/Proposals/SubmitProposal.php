<?php

namespace App\Actions\Proposals;

use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Models\Project;
use App\Models\ReviewDecision;
use App\Models\User;
use App\Notifications\ProposalWorkflowNotification;
use App\Services\AuditService;
use App\Services\ProjectNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitProposal
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ProjectNotifier $notifier,
        private readonly AssignOrganizationMentors $assignOrganizationMentors,
    ) {}

    public function handle(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor): void {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);

            if (! in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)) {
                throw ValidationException::withMessages(['proposal' => 'Proposal hanya bisa diajukan dari status draf atau perlu revisi.']);
            }

            $requiredFields = [
                'title' => 'Judul proposal',
                'theme' => 'Tema improvement',
                'theme_category' => 'Kategori tema',
                'background' => 'Latar belakang',
                'current_situation' => 'Situasi saat ini & baseline',
                'improvement_plan' => 'Rencana perbaikan',
                'target' => 'Target/tujuan',
                'scope' => 'Ruang lingkup proyek',
            ];
            $missing = [];

            foreach ($requiredFields as $field => $label) {
                if (blank($project->{$field})) {
                    $missing[$field] = "{$label} wajib diisi sebelum proposal diajukan.";
                }
            }

            $goals = array_filter($project->learning_goals_json ?? [], fn ($goal): bool => filled($goal));

            if (count($goals) !== 3) {
                $missing['learning_goals'] = 'Isi tepat 3 hal yang akan dipelajari sebelum proposal diajukan.';
            }

            $plannedSteps = array_filter(
                $project->step_timeline_json ?? [],
                fn (array $step): bool => filled($step['start_date'] ?? null) && filled($step['target_date'] ?? null),
            );

            if (count($plannedSteps) !== count(QccStep::cases())) {
                $missing['step_timeline'] = 'Isi tanggal mulai dan target selesai semua step QCC sebelum proposal diajukan.';
            }

            if (! $project->requirements()->exists()) {
                $missing['requirements'] = 'Tambahkan minimal satu kebutuhan kompetensi sebelum proposal diajukan.';
            }

            if ($missing !== []) {
                throw ValidationException::withMessages($missing);
            }

            $previousStatus = $project->proposal_status;
            $project->forceFill([
                'proposal_status' => ProposalStatus::Submitted,
                'submitted_at' => now(),
                'approved_at' => null,
                'lock_version' => $project->lock_version + 1,
            ])->save();

            ReviewDecision::query()->create([
                'project_id' => $project->id,
                'actor_id' => $actor->id,
                'decision' => $previousStatus === ProposalStatus::RevisionRequired ? 'RESUBMITTED' : 'SUBMITTED',
                'previous_status' => $previousStatus->value,
                'new_status' => ProposalStatus::Submitted->value,
                'decided_at' => now(),
            ]);

            $this->audit->record(
                'proposal.submitted',
                $project,
                before: ['status' => $previousStatus->value],
                after: ['status' => ProposalStatus::Submitted->value],
                subjectLabel: 'Proposal PRP-'.sprintf('%04d', $project->id),
            );

            $this->assignOrganizationMentors->handle($project);

            if (ProposalWorkflowNotification::enabled()) {
                $resubmitted = $previousStatus === ProposalStatus::RevisionRequired;

                DB::afterCommit(fn () => $this->proposalReviewers($project)->each(fn (User $reviewer) => $reviewer->notify(new ProposalWorkflowNotification(
                    $project->id,
                    $resubmitted ? 'Proposal revisi menunggu review' : 'Proposal baru menunggu review',
                    $resubmitted ? $project->title.' sudah direvisi dan diajukan kembali.' : $project->title.' telah diajukan dan siap direview.',
                    route('proposals.show', $project, false),
                    $resubmitted ? 'RESUBMITTED' : 'SUBMITTED',
                ))));
            }
        });
    }

    /**
     * The proposal goes to its LOB/Div mentors. Only when the organization has no mentor yet
     * does it go to everyone who oversees all proposals, so it is never left unseen.
     *
     * @return Collection<int, User>
     */
    private function proposalReviewers(Project $project): Collection
    {
        $mentors = User::query()
            ->where('is_active', true)
            ->whereHas('reviewAssignments', fn ($assignments) => $assignments->active()
                ->where('project_id', $project->id)
                ->where('scope', 'proposal'))
            ->get();

        return $mentors->isNotEmpty() ? $mentors : $this->notifier->reviewers($project, 'proposal');
    }
}
