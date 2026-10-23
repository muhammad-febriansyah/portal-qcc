<?php

namespace App\Policies;

use App\Models\ReviewAssignment;
use App\Models\ScoringRun;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * AI scores are restricted. Mentors, team leaders and members get none of these
 * permissions by default (DEC-14).
 */
class ScoringRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('scoring.view');
    }

    public function view(User $user, ScoringRun $run): bool
    {
        return $user->can('scoring.view')
            && ($user->can('projects.viewAny') || $this->hasAssignment($user, $run))
            && $run->project !== null
            && Gate::forUser($user)->allows('view', $run->project);
    }

    public function review(User $user, ScoringRun $run): bool
    {
        return ! $user->hasRole('Mentor')
            && $user->can('scoring.review')
            && ($user->can('projects.viewAny') || $this->hasAssignment($user, $run))
            && $run->project !== null
            && Gate::forUser($user)->allows('view', $run->project);
    }

    public function approve(User $user, ScoringRun $run): bool
    {
        return ! $user->hasRole('Mentor')
            && $user->can('scoring.approve')
            && ($user->can('projects.viewAny') || $this->hasAssignment($user, $run))
            && $run->project !== null
            && Gate::forUser($user)->allows('view', $run->project);
    }

    private function hasAssignment(User $user, ScoringRun $run): bool
    {
        return ReviewAssignment::query()->active()
            ->where('reviewer_id', $user->id)
            ->where('project_id', $run->project_id)
            ->where('scope', 'scoring')
            ->exists();
    }
}
