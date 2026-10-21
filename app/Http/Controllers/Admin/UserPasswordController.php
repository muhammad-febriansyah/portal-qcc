<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserPasswordController extends Controller
{
    /**
     * Issue a new temporary password. It is shown once and never stored in plain text.
     */
    public function update(User $user, AuditService $audit): RedirectResponse
    {
        Gate::authorize('update', $user);

        $password = Str::password(12, symbols: false);
        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        $audit->record('user.password_reset', $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password berhasil direset.']);
        Inertia::flash('credential', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $password,
            'reason' => 'reset',
        ]);

        return back();
    }
}
