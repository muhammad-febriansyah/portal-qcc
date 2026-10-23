<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    /**
     * Send team members to their profile until the details used for team matching are filled in.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->mustCompleteProfile()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Lengkapi profil dulu untuk membuka menu lain.']);

            return redirect()->route('profile.edit');
        }

        return $next($request);
    }
}
