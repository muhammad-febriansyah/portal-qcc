<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRubricSetRequest;
use App\Http\Requests\UpdateRubricSetRequest;
use App\Models\RubricSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RubricSetController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('rubrics.manage');

        return Inertia::render('admin/rubrics/index', [
            'rubrics' => RubricSet::query()
                ->with(['versions' => fn ($query) => $query->orderByDesc('version_number')->limit(1), 'versions.criteria'])
                ->orderBy('scoring_type')
                ->get()
                ->map(fn (RubricSet $rubricSet): array => [
                    'id' => $rubricSet->id,
                    'scoring_type' => $rubricSet->scoring_type,
                    'name' => $rubricSet->name,
                    'versions' => $rubricSet->versions->map(fn ($version): array => [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                        'publication_status' => $version->publication_status,
                        'criteria' => $version->criteria->map(fn ($criterion): array => [
                            'code' => $criterion->code,
                            'name' => $criterion->name,
                            'description' => $criterion->description,
                            'weight_pct' => (string) $criterion->weight_pct,
                        ])->values(),
                    ])->values(),
                ])
                ->values(),
            'scoringTypes' => [
                ['value' => 'proposal', 'label' => 'Proposal'],
                ['value' => 'final_presentation', 'label' => 'Final Presentation'],
                ['value' => 'convention', 'label' => 'Convention'],
            ],
            'publishingEnabled' => false,
        ]);
    }

    public function store(StoreRubricSetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $rubricSet = RubricSet::query()->create([
                'scoring_type' => $validated['scoring_type'],
                'name' => $validated['name'],
            ]);

            $version = $rubricSet->versions()->create([
                'version_number' => 1,
                'publication_status' => 'draft',
            ]);

            $version->criteria()->createMany($this->criteriaRows($validated['criteria']));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft rubrik berhasil ditambahkan.']);

        return to_route('admin.rubrics.index');
    }

    public function update(UpdateRubricSetRequest $request, RubricSet $rubricSet): RedirectResponse
    {
        Gate::authorize('rubrics.manage');

        $version = $rubricSet->versions()
            ->where('publication_status', 'draft')
            ->orderByDesc('version_number')
            ->firstOrFail();
        abort_if($rubricSet->versions()->where('publication_status', 'published')->exists(), 403);

        $validated = $request->validated();

        DB::transaction(function () use ($rubricSet, $version, $validated): void {
            $rubricSet->update(['name' => $validated['name']]);
            $version->criteria()->delete();
            $version->criteria()->createMany($this->criteriaRows($validated['criteria']));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft rubrik berhasil diperbarui.']);

        return to_route('admin.rubrics.index');
    }

    /**
     * @param  list<array{code: string, name: string, description?: ?string, weight_pct: numeric-string|int|float}>  $criteria
     * @return list<array{code: string, name: string, description: ?string, weight_pct: numeric-string|int|float, position: int}>
     */
    private function criteriaRows(array $criteria): array
    {
        return array_map(fn (array $criterion, int $index): array => [
            'code' => strtoupper($criterion['code']),
            'name' => $criterion['name'],
            'description' => $criterion['description'] ?? null,
            'weight_pct' => $criterion['weight_pct'],
            'position' => $index + 1,
        ], $criteria, array_keys($criteria));
    }
}
