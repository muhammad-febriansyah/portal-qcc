<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserPasswordController extends Controller
{
    public function update(ResetUserPasswordRequest $request, User $user, AuditService $audit): RedirectResponse
    {
        $user->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();
        $audit->record('user.password_reset', $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password berhasil direset.']);

        return back();
    }
}
