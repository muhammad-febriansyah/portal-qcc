<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminPermissionSeeder extends Seeder
{
    /**
     * Permissions granted to Super Admin for master data management.
     * Idempotent: safe to run repeatedly.
     *
     * @var list<string>
     */
    private const SUPER_ADMIN_PERMISSIONS = [
        'batches.manage',
        'competencies.manage',
        'competencies.verify',
        'users.manage',
        'audit.view',
        'organizations.manage',
        'roles.manage',
        'rubrics.manage',
        'rubrics.publish',
        'system-settings.view',
        'system-settings.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::findOrCreate('Super Admin', 'web');

        foreach (self::SUPER_ADMIN_PERMISSIONS as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
    }
}
