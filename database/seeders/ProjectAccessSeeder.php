<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProjectAccessSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'Super Admin' => ['projects.view', 'projects.viewAny'],
        'Owner QCC' => ['projects.view', 'projects.viewAny'],
        'Mentor' => ['projects.view'],
        'Team Leader' => ['projects.view', 'projects.create', 'projects.update'],
        'SIMer / Member' => ['projects.view', 'projects.create', 'projects.update'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            foreach ($permissions as $name) {
                $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
            }
        }
    }
}
