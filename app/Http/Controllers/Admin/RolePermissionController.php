<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    /**
     * The five roles approved as the application's fixed global role set.
     *
     * @var list<string>
     */
    private const ROLES = [
        'Super Admin',
        'Owner QCC',
        'Mentor',
        'Team Leader',
        'SIMer / Member',
    ];

    private const ROLE_ORDER_SQL = "CASE name WHEN 'Super Admin' THEN 0 WHEN 'Owner QCC' THEN 1 WHEN 'Mentor' THEN 2 WHEN 'Team Leader' THEN 3 WHEN 'SIMer / Member' THEN 4 END";

    public function index(): Response
    {
        Gate::authorize('roles.manage');

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::ROLES)
            ->with('permissions:id,name')
            ->orderByRaw(self::ROLE_ORDER_SQL)
            ->get(['id', 'name'])
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions
                    ->pluck('name')
                    ->sort()
                    ->values(),
            ])
            ->values();

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'permissionCatalog' => Permission::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    public function update(
        UpdateRolePermissionsRequest $request,
        Role $role,
        AuditService $audit,
        PermissionRegistrar $permissionRegistrar,
    ): RedirectResponse {
        $validated = $request->validated();
        $permissions = array_values(array_unique($validated['permissions']));
        sort($permissions);

        if ($role->name !== 'Super Admin' && in_array('rubrics.publish', $permissions, true)) {
            return back()->withErrors([
                'permissions' => 'Hanya role Super Admin yang dapat menerbitkan rubrik.',
            ]);
        }

        if ($role->name === 'Super Admin' && ! in_array('roles.manage', $permissions, true)) {
            return back()->withErrors([
                'permissions' => 'Super Admin harus tetap memiliki akses menu Role & Permission.',
            ]);
        }

        DB::transaction(function () use ($role, $permissions, $audit): void {
            $lockedRole = Role::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            $before = $lockedRole->permissions()->orderBy('name')->pluck('name')->all();

            if ($before === $permissions) {
                return;
            }

            $lockedRole->syncPermissions($permissions);

            $audit->record(
                'role.permissions_updated',
                $lockedRole,
                before: ['permissions' => $before],
                after: ['permissions' => $permissions],
                subjectLabel: $lockedRole->name,
            );

        });

        $permissionRegistrar->forgetCachedPermissions();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Permission role berhasil diperbarui.',
        ]);

        return to_route('admin.roles.index');
    }
}
