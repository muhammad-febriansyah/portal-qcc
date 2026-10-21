<?php

namespace App\Policies;

use App\Models\QccBatch;
use App\Models\User;

class QccBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('batches.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('batches.manage');
    }

    public function update(User $user, QccBatch $batch): bool
    {
        return $user->can('batches.manage');
    }

    public function delete(User $user, QccBatch $batch): bool
    {
        return $user->can('batches.manage');
    }
}
