<?php

namespace App\Http\Controllers;

use App\Enums\CompetencyLevel;
use App\Http\Requests\StoreMemberCompetencyRequest;
use App\Http\Requests\UpdateMemberCompetencyRequest;
use App\Models\Competency;
use App\Models\CompetencyGroup;
use App\Models\MemberCompetency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MyCompetencyController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $groups = CompetencyGroup::query()->orderBy('code')->get(['id', 'code', 'name']);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'group' => ['nullable', Rule::in(['all', ...$groups->pluck('id')->map(fn (int $id): string => (string) $id)->all()])],
            'sort' => ['nullable', Rule::in(['code', 'self_level', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $group = $filters['group'] ?? 'all';
        $sort = $filters['sort'] ?? 'code';
        $direction = $filters['direction'] ?? 'asc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $sortColumn = $sort === 'code' ? 'competency_master.code' : "member_competencies.{$sort}";

        $rows = MemberCompetency::query()
            ->join('competency_master', 'competency_master.id', '=', 'member_competencies.competency_id')
            ->where('member_competencies.user_id', $user->id)
            ->select('member_competencies.*')
            ->with('competency:id,group_id,code,name', 'competency.group:id,code,name')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('competency_master.code', 'like', "%{$search}%")
                        ->orWhere('competency_master.name', 'like', "%{$search}%");
                });
            })
            ->when($group !== 'all', fn (Builder $query): Builder => $query->where('competency_master.group_id', (int) $group))
            ->orderBy($sortColumn, $direction)
            ->orderBy('member_competencies.id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('my-competencies/index', [
            'competencies' => $rows->through(fn (MemberCompetency $row): array => [
                'id' => $row->id,
                'code' => $row->competency->code,
                'name' => $row->competency->name,
                'group_code' => $row->competency->group?->code,
                'self_level' => $row->self_level,
                'self_level_label' => CompetencyLevel::labelFor($row->self_level),
                'verified_level' => $row->verified_level,
                'verified_level_label' => CompetencyLevel::labelFor($row->verified_level),
                'updated_at' => $row->updated_at?->toIso8601String(),
            ]),
            'filters' => [
                'search' => $search,
                'group' => $group,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'groupOptions' => $groups->map(fn (CompetencyGroup $item): array => [
                'id' => $item->id,
                'label' => $item->name,
            ])->all(),
            'competencyOptions' => Competency::query()
                ->where('is_active', true)
                ->whereNotIn('id', MemberCompetency::query()->where('user_id', $user->id)->select('competency_id'))
                ->orderBy('code')
                ->get(['id', 'group_id', 'code', 'name'])
                ->map(fn (Competency $competency): array => [
                    'id' => $competency->id,
                    'group_id' => $competency->group_id,
                    'label' => $competency->code.' - '.$competency->name,
                ])
                ->all(),
            'levelOptions' => CompetencyLevel::options(),
        ]);
    }

    public function store(StoreMemberCompetencyRequest $request): RedirectResponse
    {
        $request->user()->memberCompetencies()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kompetensi berhasil ditambahkan.']);

        return back();
    }

    public function update(UpdateMemberCompetencyRequest $request, MemberCompetency $memberCompetency): RedirectResponse
    {
        $memberCompetency->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Level kompetensi berhasil diperbarui.']);

        return back();
    }

    public function destroy(Request $request, MemberCompetency $memberCompetency): RedirectResponse
    {
        Gate::authorize('delete', $memberCompetency);

        if ($memberCompetency->verified_level !== null) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Kompetensi yang sudah terverifikasi tidak dapat dihapus.',
            ]);

            return back();
        }

        $memberCompetency->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kompetensi berhasil dihapus dari daftar Anda.']);

        return back();
    }
}
