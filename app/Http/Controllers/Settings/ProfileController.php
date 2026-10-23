<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Organization;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'profileComplete' => $user->hasCompleteProfile(),
            'profile' => [
                'sim_id' => $user?->sim_id,
                'position' => $user?->position,
                'work_location' => $user?->work_location,
                'organization_id' => $user?->organization_id,
                'organization_name' => $user?->organization()->value('name'),
                'availability_status' => $user?->availability_status,
                'qcc_experience_batches' => $user->qcc_experience_batches ?? [],
            ],
            'organizations' => Organization::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Organization $organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                ])
                ->all(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $wasIncomplete = $request->user()->mustCompleteProfile();

        $request->user()->fill($request->validated());

        $emailChanged = $request->user()->isDirty('email');

        if ($emailChanged) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        if ($emailChanged) {
            $request->user()->sendEmailVerificationNotification();
        }

        if ($wasIncomplete && $request->user()->hasCompleteProfile()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Profil lengkap. Selamat datang di portal QCC.']);

            return to_route('dashboard');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $emailChanged ? 'Profil diperbarui. Email verifikasi dikirim ke alamat baru.' : __('Profile updated.'),
        ]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
