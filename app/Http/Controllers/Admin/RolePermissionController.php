<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

    public function index(): Response
    {
        Gate::authorize('roles.manage');

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::ROLES)
            ->with('permissions:id,name')
            ->orderByRaw('CASE name '.$this->roleOrderSql().' END')
            ->get(['id', 'name'])
            ->map(fn (Role $role): array => [
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

    private function roleOrderSql(): string
    {
        return implode(' ', array_map(
            fn (string $name, int $index): string => "WHEN '".str_replace("'", "''", $name)."' THEN {$index}",
            self::ROLES,
            array_keys(self::ROLES),
        ));
    }
}
