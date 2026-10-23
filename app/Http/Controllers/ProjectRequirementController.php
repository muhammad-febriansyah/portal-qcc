<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Http\Requests\SaveProjectRequirementsRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProjectRequirementController extends Controller
{
    /**
     * Replace the competencies a draft proposal asks for.
     * Rows that stay keep their notes and priority; removed ones are deleted.
     */
    public function update(SaveProjectRequirementsRequest $request, Project $project): RedirectResponse
    {
        abort_unless(in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true), 404);

        $wanted = collect($request->requirements())
            ->mapWithKeys(fn (array $item): array => [
                (int) $item['competency_id'] => isset($item['minimum_level']) ? (int) $item['minimum_level'] : null,
            ]);

        DB::transaction(function () use ($project, $wanted): void {
            Project::query()->whereKey($project->id)->lockForUpdate()->first();

            $project->requirements()->whereNotIn('competency_id', $wanted->keys())->delete();

            foreach ($wanted as $competencyId => $level) {
                $project->requirements()->updateOrCreate(
                    ['competency_id' => $competencyId],
                    ['minimum_level' => $level],
                );
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kebutuhan kompetensi berhasil disimpan.']);

        return back();
    }
}
