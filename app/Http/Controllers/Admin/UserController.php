<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $roleNames = Role::query()->orderBy('name')->pluck('name')->all();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['all', ...$roleNames])],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['name', 'sim_id', 'email', 'is_active', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $role = $filters['role'] ?? 'all';
        $status = $filters['status'] ?? 'all';
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $users = User::query()
            ->select(['id', 'name', 'sim_id', 'email', 'organization_id', 'is_active', 'updated_at'])
            ->with(['organization:id,name', 'roles:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sim_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== 'all', fn (Builder $query): Builder => $query->role($role))
            ->when($status !== 'all', fn (Builder $query): Builder => $query->where('is_active', $status === 'active'))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'sim_id' => $user->sim_id,
                'email' => $user->email,
                'organization_id' => $user->organization_id,
                'organization_name' => $user->organization?->name,
                'role' => $user->roles->pluck('name')->sort()->first(),
                'is_active' => $user->is_active,
                'is_self' => $user->is($request->user()),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ]),
            'filters' => [
                'search' => $search,
                'role' => $role,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'roleOptions' => $roleNames,
            'organizationOptions' => Organization::query()
                ->orderBy('name')
                ->get(['id', 'name', 'is_active'])
                ->map(fn (Organization $organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'is_active' => $organization->is_active,
                ])
                ->all(),
        ]);
    }

    public function store(StoreUserRequest $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $password = Str::password(12, symbols: false);

        $user = DB::transaction(function () use ($data, $password, $audit): User {
            $user = new User([
                'name' => $data['name'],
                'sim_id' => $data['sim_id'],
                'email' => $data['email'],
                'organization_id' => $data['organization_id'],
                'password' => $password,
            ]);
            $user->email_verified_at = now();
            $user->is_active = true;
            $user->save();
            $user->syncRoles([$data['role']]);
            $audit->record('user.role_changed', $user, before: ['role' => null], after: ['role' => $data['role']]);

            return $user;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anggota berhasil ditambahkan.']);
        Inertia::flash('credential', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $password,
            'reason' => 'created',
        ]);

        return back();
    }

    public function update(UpdateUserRequest $request, User $user, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($user, $data, $audit): void {
            $previousRole = $user->getRoleNames()->sort()->first();

            $user->update([
                'name' => $data['name'],
                'sim_id' => $data['sim_id'],
                'email' => $data['email'],
                'organization_id' => $data['organization_id'],
            ]);
            $user->syncRoles([$data['role']]);

            if ($previousRole !== $data['role']) {
                $audit->record('user.role_changed', $user, before: ['role' => $previousRole], after: ['role' => $data['role']]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data anggota berhasil diperbarui.']);

        return back();
    }
}
