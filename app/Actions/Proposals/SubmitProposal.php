<?php

namespace App\Actions\Proposals;

use App\Actions\Teams\TeamRuleValidator;
use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\ReviewDecision;
use App\Models\TeamMatchingRun;
use App\Models\User;
use App\Notifications\ProposalWorkflowNotification;
use App\Services\AuditService;
use App\Services\ProjectNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitProposal
{
    public function __construct(
        private readonly TeamRuleValidator $teamRules,
        private readonly AuditService $audit,
        private readonly ProjectNotifier $notifier,
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
                'background' => 'Latar belakang',
                'problem_statement' => 'Pernyataan masalah',
                'current_situation' => 'Kondisi saat ini',
                'baseline' => 'Baseline',
                'target' => 'Target',
                'scope' => 'Ruang lingkup',
                'rca' => 'Analisis akar masalah',
                'improvement_plan' => 'Rencana improvement',
                'business_benefit' => 'Manfaat bisnis',
            ];
            $missing = [];

            foreach ($requiredFields as $field => $label) {
                if (blank($project->{$field})) {
                    $missing[$field] = "{$label} wajib diisi sebelum proposal diajukan.";
                }
            }

            $goals = array_filter($project->learning_goals_json ?? [], fn ($goal): bool => filled($goal));

            if (count($goals) !== 3) {
                $missing['learning_goals'] = 'Isi tepat 3 tujuan pembelajaran sebelum proposal diajukan.';
            }

            if ($missing !== []) {
                throw ValidationException::withMessages($missing);
            }

            $confirmed = TeamMatchingRun::query()
                ->where('project_id', $project->id)
                ->whereNotNull('confirmed_at')
                ->exists();

            if (! $confirmed) {
                throw ValidationException::withMessages(['team' => 'Konfirmasi tim proposal terlebih dahulu.']);
            }

            $members = DB::table('project_members')
                ->join('users', 'users.id', '=', 'project_members.user_id')
                ->where('project_members.project_id', $project->id)
                ->where('project_members.status', 'ACTIVE')
                ->whereNull('project_members.left_at')
                ->where('users.is_active', true)
                ->get(['project_members.user_id', 'users.organization_id', 'project_members.role_in_project'])
                ->map(fn (object $member): array => [
                    'user_id' => (int) $member->user_id,
                    'organization_id' => $member->organization_id === null ? null : (int) $member->organization_id,
                    'role_in_project' => $member->role_in_project,
                ])->all();

            $this->teamRules->validate($members);

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

            if (ProposalWorkflowNotification::enabled()) {
                DB::afterCommit(fn () => $this->notifier->reviewers($project, 'proposal')->each(fn (User $reviewer) => $reviewer->notify(new ProposalWorkflowNotification(
                    $project->id,
                    'Proposal baru menunggu review',
                    $project->title.' telah diajukan dan siap direview.',
                    route('proposals.show', $project, false),
                    'SUBMITTED',
                ))));
            }
        });
    }
}
