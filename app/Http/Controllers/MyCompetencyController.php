<?php

namespace App\Http\Controllers;

use App\Enums\CompetencyLevel;
use App\Http\Requests\StoreMemberCompetencyRequest;
use App\Http\Requests\UpdateMemberCompetencyRequest;
use App\Models\Competency;
use App\Models\MemberCompetency;
use App\Models\QccBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MyCompetencyController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['code', 'self_level', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $sort = $filters['sort'] ?? 'code';
        $direction = $filters['direction'] ?? 'asc';
        $perPage = (int) ($filters['per_page'] ?? 10);
        $competencyCount = $user->memberCompetencies()->count();
        $batch = QccBatch::current();
        $editingIsOpen = $batch?->acceptsCompetencyChanges() ?? true;

        $sortColumn = $sort === 'code' ? 'competency_master.code' : "member_competencies.{$sort}";

        $rows = MemberCompetency::query()
            ->join('competency_master', 'competency_master.id', '=', 'member_competencies.competency_id')
            ->where('member_competencies.user_id', $user->id)
            ->where('competency_master.is_active', true)
            ->select('member_competencies.*')
            ->with('competency:id,code,name,description')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('competency_master.code', 'like', "%{$search}%")
                        ->orWhere('competency_master.name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $direction)
            ->orderBy('member_competencies.id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('my-competencies/index', [
            'competencies' => $rows->through(fn (MemberCompetency $row): array => [
                'id' => $row->id,
                'code' => $row->competency->code,
                'name' => $row->competency->name,
                'description' => $row->competency->description,
                'self_level' => $row->self_level,
                'self_level_label' => CompetencyLevel::labelFor($row->self_level),
                'updated_at' => $row->updated_at?->toIso8601String(),
            ]),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'competencyCount' => $competencyCount,
            'mustJoinTalentPool' => $user->mustFillCompetencies(),
            'competencyLimit' => MemberCompetency::MAX_PER_USER,
            'editingIsOpen' => $editingIsOpen,
            'editingClosesOn' => $batch?->registration_ends_on?->toDateString(),
            'competencyOptions' => ! $editingIsOpen || $competencyCount >= MemberCompetency::MAX_PER_USER
                ? []
                : Competency::query()
                    ->where('is_active', true)
                    ->whereNotIn('id', MemberCompetency::query()->where('user_id', $user->id)->select('competency_id'))
                    ->orderBy('code')
                    ->get(['id', 'code', 'name', 'description'])
                    ->map(fn (Competency $competency): array => [
                        'id' => $competency->id,
                        'label' => $competency->code.' - '.$competency->name,
                        'description' => $competency->description,
                    ])
                    ->all(),
            'levelOptions' => CompetencyLevel::options(),
        ]);
    }

    public function store(StoreMemberCompetencyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        DB::transaction(function () use ($userId, $data): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            if ($user->memberCompetencies()->count() >= MemberCompetency::MAX_PER_USER) {
                throw ValidationException::withMessages([
                    'competency_id' => 'Maksimal 10 kompetensi. Hapus salah satu kompetensi sebelum menambahkan yang baru.',
                ]);
            }

            $user->memberCompetencies()->create($data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kompetensi berhasil ditambahkan.']);

        return back();
    }

    public function update(UpdateMemberCompetencyRequest $request, MemberCompetency $memberCompetency): RedirectResponse
    {
        $memberCompetency->forceFill(['self_level' => (int) $request->validated('self_level')])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Level kompetensi berhasil diperbarui.']);

        return back();
    }

    public function destroy(Request $request, MemberCompetency $memberCompetency): RedirectResponse
    {
        Gate::authorize('delete', $memberCompetency);

        $memberCompetency->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kompetensi berhasil dihapus dari daftar Anda.']);

        return back();
    }
}
