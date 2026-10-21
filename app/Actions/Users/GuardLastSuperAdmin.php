<?php

namespace App\Actions\Users;

use App\Models\User;

class GuardLastSuperAdmin
{
    public const ROLE = 'Super Admin';

    /**
     * Whether applying the change would leave the system without an active Super Admin.
     *
     * @param  list<string>  $newRoles
     */
    public function wouldRemoveLastAdmin(User $target, bool $willBeActive, array $newRoles): bool
    {
        $isActiveAdmin = $target->is_active && $target->hasRole(self::ROLE);

        if (! $isActiveAdmin) {
            return false;
        }

        if ($willBeActive && in_array(self::ROLE, $newRoles, true)) {
            return false;
        }

        return ! User::query()
            ->role(self::ROLE)
            ->where('is_active', true)
            ->whereKeyNot($target->id)
            ->exists();
    }
}
