<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureTalentPoolCompetencies
{
    /**
     * Members join the talent pool by filling in their competencies before they write a
     * proposal, so Owner QCC can find them when forming teams.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->mustFillCompetencies()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Isi minimal satu kompetensi dulu untuk masuk Talent Pool sebelum membuat proposal.']);

            return redirect()->route('my-competencies.index');
        }

        return $next($request);
    }
}
