<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAvatarRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AvatarController extends Controller
{
    public function update(UpdateAvatarRequest $request, AuditService $audit): RedirectResponse
    {
        $user = $request->user();
        $previousPath = $user->avatar_path;

        $user->forceFill([
            'avatar_path' => $request->file('avatar')->store('avatars', 'public'),
        ])->save();

        if ($previousPath !== null) {
            Storage::disk('public')->delete($previousPath);
        }

        $audit->record('user.avatar_changed', $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto profil berhasil diperbarui.']);

        return back();
    }

    public function destroy(Request $request, AuditService $audit): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
            $audit->record('user.avatar_removed', $user);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto profil berhasil dihapus.']);

        return back();
    }
}
