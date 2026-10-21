<?php

namespace App\Actions\Teams;

use Illuminate\Validation\ValidationException;

class TeamRuleValidator
{
    /**
     * Validate a proposed team before it is confirmed.
     *
     * @param  array<int, array{user_id: int, organization_id: int|null, role_in_project: string}>  $members
     *
     * @throws ValidationException
     */
    public function validate(array $members): void
    {
        $errors = [];

        if (count($members) > 5) {
            $errors[] = 'Jumlah anggota tim maksimal 5 orang, termasuk leader.';
        }

        $leaderCount = count(array_filter(
            $members,
            fn (array $member): bool => $member['role_in_project'] === 'LEADER',
        ));

        if ($leaderCount !== 1) {
            $errors[] = 'Tim harus memiliki tepat 1 leader.';
        }

        $organizationIds = array_filter(array_column($members, 'organization_id'));

        if (count(array_unique($organizationIds)) < 2) {
            $errors[] = 'Anggota tim harus berasal dari minimal 2 LOB/Divisi.';
        }

        $userIds = array_column($members, 'user_id');

        if (count(array_unique($userIds)) !== count($userIds)) {
            $errors[] = 'Anggota yang sama tidak boleh dipilih lebih dari satu kali.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'members' => $errors,
            ]);
        }
    }
}
