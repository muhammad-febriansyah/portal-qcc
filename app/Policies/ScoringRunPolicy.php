<?php

namespace App\Policies;

use App\Models\ScoringRun;
use App\Models\User;

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
        return $user->can('scoring.view');
    }

    public function run(User $user): bool
    {
        return $user->can('scoring.run');
    }

    public function review(User $user, ScoringRun $run): bool
    {
        return $user->can('scoring.review');
    }

    public function approve(User $user, ScoringRun $run): bool
    {
        return $user->can('scoring.approve');
    }
}
