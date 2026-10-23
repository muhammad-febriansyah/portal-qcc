<?php

namespace App\Policies;

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.viewAny');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can('projects.view')
            && Project::query()->visibleTo($user)->whereKey($project->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        if (! $user->can('projects.update')) {
            return false;
        }

        if ($project->proposer_id === $user->id && in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)) {
            return true;
        }

        if ($user->can('projects.viewAny')) {
            return true;
        }

        return $user->hasRole('Team Leader')
            && $project->members()
                ->where('users.id', $user->id)
                ->where('project_members.status', 'ACTIVE')
                ->whereNull('project_members.left_at')
                ->where('project_members.role_in_project', 'LEADER')
                ->exists();
    }

    /**
     * Search team candidates: reviewers, or the Team Leader who proposed the project.
     */
    public function findCandidates(User $user, Project $project): bool
    {
        return $user->can('projects.view')
            && ($user->can('projects.viewAny')
                || ($user->hasRole('Team Leader') && $project->proposer_id === $user->id));
    }

    public function submit(User $user, Project $project): bool
    {
        return $user->can('projects.submit')
            && $project->proposer_id === $user->id
            && $this->isActiveMember($user, $project);
    }

    public function updateStep(User $user, Project $project): bool
    {
        return $user->can('projects.update') && $this->isActiveMember($user, $project);
    }

    public function submitStep(User $user, Project $project): bool
    {
        return $user->can('projects.submit') && $this->isActiveMember($user, $project);
    }

    public function manageRisks(User $user, Project $project): bool
    {
        return $user->can('projects.update') && $this->isActiveMember($user, $project);
    }

    public function manageReports(User $user, Project $project): bool
    {
        return $user->can('projects.update') && $this->isActiveMember($user, $project);
    }

    public function assignMentors(User $user, Project $project): bool
    {
        return $user->can('projects.review') && $user->can('projects.viewAny');
    }

    public function writeMentoring(User $user, Project $project): bool
    {
        return $user->hasRole('Mentor') && $user->can('projects.view') && $this->isAssignedMentor($user, $project);
    }

    public function isAssignedMentor(User $user, Project $project): bool
    {
        return $project->mentorAssignments()->where('mentor_id', $user->id)->where('status', 'ACTIVE')->exists();
    }

    public function review(User $user, Project $project): bool
    {
        return $user->can('projects.review')
            && ($user->can('projects.viewAny') || $this->hasReviewAssignment($user, $project, ['proposal', 'steps', 'biweekly']));
    }

    public function reviewProposal(User $user, Project $project): bool
    {
        return ! $user->hasRole('Mentor')
            && $user->can('projects.review')
            && ($user->can('projects.viewAny') || $this->hasReviewAssignment($user, $project, ['proposal']));
    }

    public function approveProposal(User $user, Project $project): bool
    {
        return ! $user->hasRole('Mentor')
            && $user->can('projects.approve')
            && ($user->can('projects.viewAny') || $this->hasReviewAssignment($user, $project, ['proposal']));
    }

    public function reviewStep(User $user, Project $project): bool
    {
        if ($user->hasRole('Mentor')) {
            return $user->can('projects.view')
                && $user->can('projects.review')
                && $this->isAssignedMentor($user, $project);
        }

        return $user->can('projects.review')
            && ($user->can('projects.viewAny') || $this->hasReviewAssignment($user, $project, ['steps']));
    }

    public function approveStep(User $user, Project $project): bool
    {
        if ($user->hasRole('Mentor')) {
            return $user->can('projects.view')
                && $user->can('projects.review')
                && $user->can('projects.approve')
                && $this->isAssignedMentor($user, $project);
        }

        return $user->can('projects.approve')
            && ($user->can('projects.viewAny') || $this->hasReviewAssignment($user, $project, ['steps']));
    }

    public function reviewBiweekly(User $user, Project $project): bool
    {
        return ($user->can('projects.review')
                && ($user->can('projects.viewAny') || $this->hasReviewAssignment($user, $project, ['biweekly'])))
            || ($user->hasRole('Mentor')
                && $user->can('projects.view')
                && $this->isAssignedMentor($user, $project));
    }

    public function approve(User $user, Project $project): bool
    {
        return ! $user->hasRole('Mentor')
            && $user->can('projects.approve')
            && ($user->can('projects.viewAny') || $this->hasReviewAssignment($user, $project, ['proposal', 'steps']));
    }

    public function viewEvidence(User $user, Project $project): bool
    {
        return $user->can('evidence.view') && $this->view($user, $project);
    }

    public function uploadEvidence(User $user, Project $project): bool
    {
        return $user->can('evidence.upload')
            && ($user->can('projects.viewAny') || $this->isActiveMember($user, $project));
    }

    public function deleteEvidence(User $user, Project $project): bool
    {
        return $user->can('evidence.delete') && $this->view($user, $project);
    }

    public function runScoring(User $user, Project $project): bool
    {
        return $user->can('scoring.run') && $this->view($user, $project);
    }

    private function isActiveMember(User $user, Project $project): bool
    {
        $memberRoles = [];

        if ($user->hasRole('Team Leader')) {
            $memberRoles[] = 'LEADER';
        }

        if ($user->hasRole('SIMer / Member')) {
            $memberRoles[] = 'MEMBER';
        }

        return $memberRoles !== [] && $project->members()
            ->where('users.id', $user->id)
            ->where('project_members.status', 'ACTIVE')
            ->whereNull('project_members.left_at')
            ->whereIn('project_members.role_in_project', $memberRoles)
            ->exists();
    }

    /** @param list<string> $scopes */
    private function hasReviewAssignment(User $user, Project $project, array $scopes): bool
    {
        return $project->reviewAssignments()->active()
            ->where('reviewer_id', $user->id)
            ->whereIn('scope', $scopes)
            ->exists();
    }
}
