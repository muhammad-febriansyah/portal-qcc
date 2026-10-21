<?php

namespace App\Policies;

use App\Models\MemberCompetency;
use App\Models\User;

/**
 * People only manage their own self-assessment. Nobody changes it for them here.
 */
class MemberCompetencyPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MemberCompetency $memberCompetency): bool
    {
        return $memberCompetency->user_id === $user->id;
    }

    public function delete(User $user, MemberCompetency $memberCompetency): bool
    {
        return $memberCompetency->user_id === $user->id;
    }
}
