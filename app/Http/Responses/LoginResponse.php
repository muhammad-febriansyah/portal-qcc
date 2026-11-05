<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * After login a member who is not in the talent pool yet goes to fill in competencies first.
 */
class LoginResponse implements LoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        if ($request->user()?->mustFillCompetencies()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Isi kompetensi Anda untuk masuk Talent Pool.']);

            return redirect()->route('my-competencies.index');
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
