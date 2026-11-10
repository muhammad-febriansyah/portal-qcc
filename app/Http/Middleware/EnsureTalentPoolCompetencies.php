<?php

namespace App\Http\Middleware;

use App\Models\MemberCompetency;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureTalentPoolCompetencies
{
    /**
     * Members fill in at least one competency before they write a
     * proposal, so Owner QCC can find them when forming teams.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->mustFillCompetencies()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => MemberCompetency::editingIsOpen()
                ? 'Tambahkan minimal 1 kompetensi sebelum membuat proposal.'
                : 'Batas waktu pengisian kompetensi sudah lewat, jadi Anda belum bisa membuat proposal.']);

            return redirect()->route('my-competencies.index');
        }

        return $next($request);
    }
}
