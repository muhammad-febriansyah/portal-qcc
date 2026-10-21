<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the change password page. The update itself requires the current password.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/password', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }
}
