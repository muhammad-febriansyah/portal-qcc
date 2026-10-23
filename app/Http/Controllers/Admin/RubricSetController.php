<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRubricSetRequest;
use App\Http\Requests\UpdateRubricSetRequest;
use App\Models\RubricCriterion;
use App\Models\RubricSet;
use App\Models\RubricVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                ->with(['versions' => fn ($query) => $query->orderByDesc('version_number'), 'versions.criteria'])
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
            'canPublish' => request()->user()?->can('rubrics.publish') ?? false,
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

        $version = $rubricSet->versions()->orderByDesc('version_number')->firstOrFail();
        abort_unless($version->publication_status === 'draft', 403);

        $validated = $request->validated();

        DB::transaction(function () use ($rubricSet, $version, $validated): void {
            $rubricSet->update(['name' => $validated['name']]);
            $version->criteria()->get()->each(fn (RubricCriterion $criterion) => $criterion->delete());
            $version->criteria()->createMany($this->criteriaRows($validated['criteria']));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft rubrik berhasil diperbarui.']);

        return to_route('admin.rubrics.index');
    }

    public function createVersion(RubricSet $rubricSet): RedirectResponse
    {
        Gate::authorize('rubrics.manage');

        $version = DB::transaction(function () use ($rubricSet): ?RubricVersion {
            $lockedSet = RubricSet::query()->whereKey($rubricSet->getKey())->lockForUpdate()->firstOrFail();
            $latestVersion = $lockedSet->versions()
                ->with('criteria')
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->firstOrFail();

            if ($latestVersion->publication_status !== 'published') {
                return null;
            }

            $version = $lockedSet->versions()->create([
                'version_number' => $latestVersion->version_number + 1,
                'publication_status' => 'draft',
            ]);

            $version->criteria()->createMany($latestVersion->criteria->map(fn ($criterion): array => [
                'code' => $criterion->code,
                'name' => $criterion->name,
                'description' => $criterion->description,
                'weight_pct' => $criterion->weight_pct,
                'position' => $criterion->position,
            ])->all());

            return $version;
        });

        if ($version === null) {
            return back()->withErrors([
                'rubric' => 'Versi baru hanya dapat dibuat dari rubrik yang sudah dipublikasikan.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft versi baru berhasil dibuat dari versi terbit.']);

        return to_route('admin.rubrics.index');
    }

    public function publish(Request $request, RubricSet $rubricSet, RubricVersion $rubricVersion): RedirectResponse
    {
        Gate::authorize('rubrics.publish');
        abort_unless($request->user()->hasRole('Super Admin'), 403);

        abort_unless($rubricVersion->rubric_set_id === $rubricSet->id, 404);

        $published = DB::transaction(function () use ($rubricSet, $rubricVersion): bool {
            $lockedSet = RubricSet::query()->whereKey($rubricSet->getKey())->lockForUpdate()->firstOrFail();
            $lockedVersion = $lockedSet->versions()->whereKey($rubricVersion->getKey())->lockForUpdate()->firstOrFail();
            $latestVersion = $lockedSet->versions()->orderByDesc('version_number')->value('id');

            abort_unless(
                $lockedVersion->publication_status === 'draft' && $latestVersion === $lockedVersion->id,
                403,
            );

            $weightInHundredths = $lockedVersion->criteria()->get()->sum(
                fn ($criterion): int => (int) round(((float) $criterion->weight_pct) * 100),
            );

            if ($lockedVersion->criteria()->count() === 0 || $weightInHundredths !== 10000) {
                return false;
            }

            $lockedVersion->update(['publication_status' => 'published']);

            return true;
        });

        if (! $published) {
            return back()->withErrors([
                'rubric' => 'Rubrik hanya dapat dipublikasikan jika memiliki kriteria dan total bobot tepat 100%.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Versi rubrik berhasil dipublikasikan.']);

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
