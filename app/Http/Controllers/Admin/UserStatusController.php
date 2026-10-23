<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\GuardLastSuperAdmin;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class UserStatusController extends Controller
{
    public function update(Request $request, User $user, GuardLastSuperAdmin $guard): RedirectResponse
    {
        Gate::authorize('update', $user);

        $isActive = $request->validate(['is_active' => ['required', 'boolean']])['is_active'];

        if (! $isActive && $user->is($request->user())) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.']);

            return back();
        }

        if ($guard->wouldRemoveLastAdmin($user, (bool) $isActive, array_values($user->getRoleNames()->all()))) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Super Admin aktif terakhir tidak dapat dinonaktifkan.']);

            return back();
        }

        $user->forceFill(['is_active' => $isActive])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isActive ? 'Akun berhasil diaktifkan.' : 'Akun berhasil dinonaktifkan.',
        ]);

        return back();
    }
}
