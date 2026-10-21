<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompetencyLevel;
use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\CompetencyGroup;
use App\Models\MemberCompetency;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Look people up by what they can do. Work data only: no personal contacts.
 * The self assessed and the verified level are always shown apart.
 */
class CompetencyDirectoryController extends Controller
{
    private const EFFECTIVE_LEVEL = 'COALESCE(member_competencies.verified_level, member_competencies.self_level)';

    public function index(Request $request): Response
    {
        Gate::authorize('people.report');

        $filters = $request->validate([
            'view' => ['nullable', Rule::in(['competency', 'member'])],
            'search' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'regex:/^(all|\d+)$/'],
            'group' => ['nullable', 'string', 'regex:/^(all|\d+)$/'],
            'competency' => ['nullable', 'string', 'regex:/^(all|\d+)$/'],
            'min_level' => ['nullable', 'integer', 'between:0,4'],
            'verified_only' => ['nullable', 'boolean'],
            'filled' => ['nullable', Rule::in(['all', 'filled', 'empty'])],
            'sort' => ['nullable', Rule::in(['name', 'level', 'competency', 'updated_at', 'competencies_count'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
            'member' => ['nullable', 'integer'],
        ]);

        $view = $filters['view'] ?? 'competency';
        $state = [
            'view' => $view,
            'search' => trim((string) ($filters['search'] ?? '')),
            'organization' => $filters['organization'] ?? 'all',
            'group' => $filters['group'] ?? 'all',
            'competency' => $filters['competency'] ?? 'all',
            'min_level' => (int) ($filters['min_level'] ?? 0),
            'verified_only' => (bool) ($filters['verified_only'] ?? false),
            'filled' => $filters['filled'] ?? 'all',
            'sort' => $filters['sort'] ?? ($view === 'competency' ? 'level' : 'name'),
            'direction' => $filters['direction'] ?? ($view === 'competency' ? 'desc' : 'asc'),
            'per_page' => (int) ($filters['per_page'] ?? 10),
        ];

        return Inertia::render('admin/competency-directory/index', [
            'rows' => $view === 'competency' ? $this->byCompetency($state) : $this->byMember($state),
            'filters' => $state,
            'selectedMember' => isset($filters['member']) ? $this->memberProfile((int) $filters['member']) : null,
            'organizationOptions' => Organization::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Organization $organization): array => ['id' => $organization->id, 'label' => $organization->name])->all(),
            'groupOptions' => CompetencyGroup::query()->orderBy('code')->get(['id', 'name'])
                ->map(fn (CompetencyGroup $group): array => ['id' => $group->id, 'label' => $group->name])->all(),
            'competencyOptions' => Competency::query()->where('is_active', true)->orderBy('code')->get(['id', 'group_id', 'code', 'name'])
                ->map(fn (Competency $competency): array => [
                    'id' => $competency->id,
                    'group_id' => $competency->group_id,
                    'label' => $competency->code.' - '.$competency->name,
                ])->all(),
            'levelOptions' => CompetencyLevel::options(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function byCompetency(array $state)
    {
        $sortColumn = match ($state['sort']) {
            'name' => 'users.name',
            'competency' => 'competency_master.code',
            'updated_at' => 'member_competencies.updated_at',
            default => 'effective_level',
        };

        return MemberCompetency::query()
            ->join('users', 'users.id', '=', 'member_competencies.user_id')
            ->join('competency_master', 'competency_master.id', '=', 'member_competencies.competency_id')
            ->where('users.is_active', true)
            ->select('member_competencies.*')
            ->selectRaw(self::EFFECTIVE_LEVEL.' as effective_level')
            ->with([
                'user:id,name,sim_id,email,organization_id',
                'user.organization:id,name',
                'user.roles:id,name',
                'competency:id,group_id,code,name',
                'competency.group:id,code',
            ])
            ->when($state['search'] !== '', function (Builder $query) use ($state): void {
                $query->where(function (Builder $query) use ($state): void {
                    $query->where('users.name', 'like', "%{$state['search']}%")
                        ->orWhere('users.sim_id', 'like', "%{$state['search']}%")
                        ->orWhere('users.email', 'like', "%{$state['search']}%");
                });
            })
            ->when($state['organization'] !== 'all', fn (Builder $query): Builder => $query->where('users.organization_id', (int) $state['organization']))
            ->when($state['competency'] !== 'all', fn (Builder $query): Builder => $query->where('member_competencies.competency_id', (int) $state['competency']))
            ->when($state['group'] !== 'all', fn (Builder $query): Builder => $query->where('competency_master.group_id', (int) $state['group']))
            ->when($state['min_level'] > 0, fn (Builder $query): Builder => $query->whereRaw(self::EFFECTIVE_LEVEL.' >= ?', [$state['min_level']]))
            ->when($state['verified_only'], fn (Builder $query): Builder => $query->whereNotNull('member_competencies.verified_level'))
            ->orderBy($sortColumn, $state['direction'])
            ->orderBy('users.name')
            ->orderBy('member_competencies.id')
            ->paginate($state['per_page'])
            ->withQueryString()
            ->through(fn (MemberCompetency $row): array => [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'name' => $row->user->name,
                'sim_id' => $row->user->sim_id,
                'email' => $row->user->email,
                'organization' => $row->user->organization?->name,
                'role' => $row->user->roles->pluck('name')->sort()->first(),
                'competency_code' => $row->competency->code,
                'competency_name' => $row->competency->name,
                'group_code' => $row->competency->group?->code,
                'self_level' => $row->self_level,
                'self_level_label' => CompetencyLevel::labelFor($row->self_level),
                'verified_level' => $row->verified_level,
                'verified_level_label' => CompetencyLevel::labelFor($row->verified_level),
            ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function byMember(array $state)
    {
        $holdsFiltered = $state['competency'] !== 'all' || $state['group'] !== 'all' || $state['min_level'] > 0 || $state['verified_only'];

        return User::query()
            ->select(['users.id', 'users.name', 'users.sim_id', 'users.email', 'users.organization_id'])
            ->where('users.is_active', true)
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('roles')
                    ->orWhereHas('roles', fn (Builder $roles): Builder => $roles->where('name', '!=', 'Super Admin'));
            })
            ->withCount([
                'memberCompetencies as competencies_count',
                'memberCompetencies as verified_count' => fn (Builder $query): Builder => $query->whereNotNull('verified_level'),
            ])
            ->with(['organization:id,name', 'roles:id,name'])
            ->when($state['search'] !== '', function (Builder $query) use ($state): void {
                $query->where(function (Builder $query) use ($state): void {
                    $query->where('users.name', 'like', "%{$state['search']}%")
                        ->orWhere('users.sim_id', 'like', "%{$state['search']}%")
                        ->orWhere('users.email', 'like', "%{$state['search']}%");
                });
            })
            ->when($state['organization'] !== 'all', fn (Builder $query): Builder => $query->where('users.organization_id', (int) $state['organization']))
            ->when($state['filled'] === 'filled', fn (Builder $query): Builder => $query->has('memberCompetencies'))
            ->when($state['filled'] === 'empty', fn (Builder $query): Builder => $query->doesntHave('memberCompetencies'))
            ->when($holdsFiltered, function (Builder $query) use ($state): void {
                $query->whereHas('memberCompetencies', function (Builder $held) use ($state): void {
                    $held->when($state['competency'] !== 'all', fn (Builder $q): Builder => $q->where('competency_id', (int) $state['competency']))
                        ->when($state['group'] !== 'all', fn (Builder $q): Builder => $q->whereHas('competency', fn (Builder $c): Builder => $c->where('group_id', (int) $state['group'])))
                        ->when($state['min_level'] > 0, fn (Builder $q): Builder => $q->whereRaw('COALESCE(verified_level, self_level) >= ?', [$state['min_level']]))
                        ->when($state['verified_only'], fn (Builder $q): Builder => $q->whereNotNull('verified_level'));
                });
            })
            ->orderBy($state['sort'] === 'competencies_count' ? 'competencies_count' : 'users.name', $state['direction'])
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate($state['per_page'])
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'sim_id' => $user->sim_id,
                'email' => $user->email,
                'organization' => $user->organization?->name,
                'role' => $user->roles->pluck('name')->sort()->first(),
                'competencies_count' => (int) $user->competencies_count,
                'verified_count' => (int) $user->verified_count,
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function memberProfile(int $userId): ?array
    {
        $user = User::query()
            ->where('is_active', true)
            ->with(['organization:id,name', 'roles:id,name'])
            ->find($userId, ['id', 'name', 'sim_id', 'email', 'organization_id']);

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'sim_id' => $user->sim_id,
            'email' => $user->email,
            'organization' => $user->organization?->name,
            'role' => $user->roles->pluck('name')->sort()->first(),
            'competencies' => MemberCompetency::query()
                ->join('competency_master', 'competency_master.id', '=', 'member_competencies.competency_id')
                ->where('member_competencies.user_id', $user->id)
                ->orderBy('competency_master.code')
                ->select('member_competencies.*')
                ->with('competency:id,group_id,code,name', 'competency.group:id,code')
                ->get()
                ->map(fn (MemberCompetency $row): array => [
                    'id' => $row->id,
                    'code' => $row->competency->code,
                    'name' => $row->competency->name,
                    'group_code' => $row->competency->group?->code,
                    'self_level' => $row->self_level,
                    'self_level_label' => CompetencyLevel::labelFor($row->self_level),
                    'verified_level' => $row->verified_level,
                    'verified_level_label' => CompetencyLevel::labelFor($row->verified_level),
                ])->all(),
        ];
    }
}
