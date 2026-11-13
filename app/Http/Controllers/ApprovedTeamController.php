<?php

namespace App\Http\Controllers;

use App\Actions\Teams\ChangeApprovedTeam;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ApprovedTeamController extends Controller
{
    public function store(Request $request, Project $project, ChangeApprovedTeam $team): RedirectResponse
    {
        Gate::authorize('findCandidates', $project);

        $validated = $request->validate(['user_id' => ['required', 'integer']]);

        $team->add($project, (int) $validated['user_id'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anggota tim berhasil ditambahkan.']);

        return back();
    }

    public function destroy(Request $request, Project $project, User $member, ChangeApprovedTeam $team): RedirectResponse
    {
        Gate::authorize('findCandidates', $project);

        $validated = $request->validate(['replacement_pic_id' => ['nullable', 'integer']]);

        $team->remove(
            $project,
            $member->id,
            $request->user(),
            isset($validated['replacement_pic_id']) ? (int) $validated['replacement_pic_id'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anggota tim berhasil dikeluarkan.']);

        return back();
    }

    public function changeLeader(Request $request, Project $project, ChangeApprovedTeam $team): RedirectResponse
    {
        Gate::authorize('findCandidates', $project);

        $validated = $request->validate(['user_id' => ['required', 'integer']]);

        $team->changeLeader($project, (int) $validated['user_id'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Leader tim berhasil diganti.']);

        return back();
    }
}
