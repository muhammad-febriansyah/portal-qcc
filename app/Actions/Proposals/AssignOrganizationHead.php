<?php

namespace App\Actions\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectMentor;
use App\Models\ReviewAssignment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * The Div or LOB head of the proposer's organization reviews the proposal and mentors the
 * project. Nobody assigns them by hand, and once a project has its head the assignment is
 * locked: a later change of head in the organization leaves the project alone.
 */
class AssignOrganizationHead
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(Project $project): ?User
    {
        if ($project->mentorAssignments()->exists()) {
            return null;
        }

        $head = $project->proposer()->with('organization.head')->first()?->organization?->head;

        if ($head === null || ! $head->is_active || $head->id === $project->proposer_id) {
            return null;
        }

        self::grantMentorRole($head);

        if (in_array($project->proposal_status, [ProposalStatus::Submitted, ProposalStatus::UnderReview, ProposalStatus::RevisionRequired], true)) {
            ReviewAssignment::query()->updateOrCreate(
                ['project_id' => $project->id, 'reviewer_id' => $head->id, 'scope' => 'proposal'],
                ['status' => ReviewAssignment::ACTIVE, 'granted_by' => null, 'expires_at' => null],
            );
        }

        $assignment = ProjectMentor::query()->create([
            'project_id' => $project->id,
            'mentor_id' => $head->id,
            'assigned_by' => null,
            'status' => 'ACTIVE',
            'assigned_at' => now(),
        ]);

        $this->audit->record('mentor.assigned', $assignment, after: ['mentor_id' => $head->id], subjectLabel: "{$head->name} untuk proposal PRP-".sprintf('%04d', $project->id));

        return $head;
    }

    /**
     * Proposals already in the process when an organization gets its head, and still without
     * a mentor, get that head too.
     */
    public function backfill(Organization $organization): void
    {
        Project::query()
            ->whereHas('proposer', fn (Builder $proposer): Builder => $proposer->where('organization_id', $organization->id))
            ->whereNotIn('proposal_status', [ProposalStatus::Draft->value, ProposalStatus::Rejected->value])
            ->whereDoesntHave('mentorAssignments')
            ->each(fn (Project $project) => $this->handle($project));
    }

    /**
     * Heads need the Mentor permissions to review, unless they already oversee every project.
     */
    public static function grantMentorRole(User $head): void
    {
        if ($head->can('projects.viewAny') || $head->hasRole('Mentor')) {
            return;
        }

        $head->assignRole(Role::findOrCreate('Mentor', 'web'));
    }
}
