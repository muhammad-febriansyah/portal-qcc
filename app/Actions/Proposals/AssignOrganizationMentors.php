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
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * The mentors of the proposer's organization review the proposal and mentor the project.
 * When an organization has two mentors, both are assigned and either one may decide.
 * Nobody assigns them by hand, and once a project has its mentors the assignment is locked:
 * a later change of mentors in the organization leaves the project alone.
 */
class AssignOrganizationMentors
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @return Collection<int, User> The mentors assigned now; empty when the project already had some.
     */
    public function handle(Project $project): Collection
    {
        if ($project->mentorAssignments()->exists()) {
            return collect();
        }

        $mentors = ($project->proposer()->with('organization.mentors')->first()?->organization?->mentors ?? collect())
            ->filter(fn (User $mentor): bool => $mentor->is_active && $mentor->id !== $project->proposer_id)
            ->values();

        foreach ($mentors as $mentor) {
            self::grantMentorRole($mentor);

            if (in_array($project->proposal_status, [ProposalStatus::Submitted, ProposalStatus::UnderReview, ProposalStatus::RevisionRequired], true)) {
                ReviewAssignment::query()->updateOrCreate(
                    ['project_id' => $project->id, 'reviewer_id' => $mentor->id, 'scope' => 'proposal'],
                    ['status' => ReviewAssignment::ACTIVE, 'granted_by' => null, 'expires_at' => null],
                );
            }

            $assignment = ProjectMentor::query()->create([
                'project_id' => $project->id,
                'mentor_id' => $mentor->id,
                'assigned_by' => null,
                'status' => 'ACTIVE',
                'assigned_at' => now(),
            ]);

            $this->audit->record('mentor.assigned', $assignment, after: ['mentor_id' => $mentor->id], subjectLabel: "{$mentor->name} untuk proposal PRP-".sprintf('%04d', $project->id));
        }

        return $mentors;
    }

    /**
     * Proposals already in the process when an organization gets its mentors, and still without
     * a mentor, get those mentors too.
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
     * Mentors need the Mentor permissions to review, unless they already oversee every project.
     */
    public static function grantMentorRole(User $mentor): void
    {
        if ($mentor->can('projects.viewAny') || $mentor->hasRole('Mentor')) {
            return;
        }

        $mentor->assignRole(Role::findOrCreate('Mentor', 'web'));
    }
}
