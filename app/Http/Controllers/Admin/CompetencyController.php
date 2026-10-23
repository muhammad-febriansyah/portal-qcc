<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompetencyRequest;
use App\Http\Requests\UpdateCompetencyRequest;
use App\Models\Competency;
use App\Models\CompetencyGroup;
use App\Services\CompetencyImportService;
use App\Services\CompetencyTemplateBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompetencyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Competency::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['code', 'name', 'group_id', 'is_active'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $group = $filters['group'] ?? 'all';
        $status = $filters['status'] ?? 'all';
        $sort = $filters['sort'] ?? 'code';
        $direction = $filters['direction'] ?? 'asc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $competencies = Competency::query()
            ->select(['id', 'group_id', 'code', 'name', 'description', 'is_active'])
            ->with(['group:id,code,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($group !== 'all', fn (Builder $query): Builder => $query->whereHas(
                'group',
                fn (Builder $groupQuery): Builder => $groupQuery->where('code', $group),
            ))
            ->when($status !== 'all', fn (Builder $query): Builder => $query->where('is_active', $status === 'active'))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('admin/competencies/index', [
            'competencies' => $competencies->through(fn (Competency $competency): array => [
                'id' => $competency->id,
                'group_id' => $competency->group_id,
                'group_code' => $competency->group->code,
                'group_name' => $competency->group->name,
                'code' => $competency->code,
                'name' => $competency->name,
                'description' => $competency->description,
                'is_active' => $competency->is_active,
            ]),
            'groups' => CompetencyGroup::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'filters' => [
                'search' => $search,
                'group' => $group,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(StoreCompetencyRequest $request): RedirectResponse
    {
        Competency::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kompetensi berhasil ditambahkan.']);

        return to_route('admin.competencies.index');
    }

    public function template(CompetencyTemplateBuilder $builder): BinaryFileResponse
    {
        Gate::authorize('competencies.manage');

        return response()
            ->download($builder->build(), 'template-master-kompetensi.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    public function import(Request $request, CompetencyImportService $importer): RedirectResponse
    {
        Gate::authorize('competencies.manage');

        $validated = $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx', 'max:5120'],
        ], [
            'file.required' => 'Pilih file Excel (.xlsx) terlebih dahulu.',
            'file.extensions' => 'File harus berformat Excel (.xlsx).',
            'file.mimes' => 'File harus berformat Excel (.xlsx).',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);
        $counts = $importer->importXlsx($validated['file']->getPathname());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Impor {$counts['total']} kompetensi selesai: {$counts['created']} dibuat, {$counts['updated']} diperbarui.",
        ]);

        return to_route('admin.competencies.index');
    }

    public function update(UpdateCompetencyRequest $request, Competency $competency): RedirectResponse
    {
        $competency->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kompetensi berhasil diperbarui.']);

        return to_route('admin.competencies.index');
    }

    public function destroy(Competency $competency): RedirectResponse
    {
        Gate::authorize('delete', $competency);

        $isInUse = DB::table('member_competencies')
            ->where('competency_id', $competency->id)
            ->exists()
            || DB::table('project_requirements')
                ->where('competency_id', $competency->id)
                ->exists();

        if ($isInUse) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Kompetensi sudah dipakai dan tidak dapat dihapus. Nonaktifkan kompetensi sebagai gantinya.',
            ]);

            return to_route('admin.competencies.index');
        }

        $competency->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kompetensi berhasil dihapus.']);

        return to_route('admin.competencies.index');
    }
}
