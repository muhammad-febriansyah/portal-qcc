<?php

namespace App\Policies;

use App\Models\MemberCompetency;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * People only manage their own self-assessment, and only until proposal submission closes.
 * Nobody changes it for them here.
 */
class MemberCompetencyPolicy
{
    public function create(User $user): Response
    {
        return $this->whileEditingIsOpen(true);
    }

    public function update(User $user, MemberCompetency $memberCompetency): Response
    {
        return $this->whileEditingIsOpen($memberCompetency->user_id === $user->id);
    }

    public function delete(User $user, MemberCompetency $memberCompetency): Response
    {
        return $this->whileEditingIsOpen($memberCompetency->user_id === $user->id);
    }

    private function whileEditingIsOpen(bool $allowed): Response
    {
        if (! $allowed) {
            return Response::deny();
        }

        return MemberCompetency::editingIsOpen()
            ? Response::allow()
            : Response::deny('Batas waktu pengisian kompetensi sudah lewat, jadi kompetensi tidak bisa diubah lagi.');
    }
}
