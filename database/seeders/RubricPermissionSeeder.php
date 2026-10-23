<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RubricPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managePermission = Permission::findOrCreate('rubrics.manage', 'web');
        $publishPermission = Permission::findOrCreate('rubrics.publish', 'web');

        Role::findOrCreate('Owner QCC', 'web')->givePermissionTo($managePermission);
        Role::findOrCreate('Super Admin', 'web')->givePermissionTo($publishPermission);

        Role::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', 'Super Admin')
            ->each(fn (Role $role) => $role->revokePermissionTo($publishPermission));
    }
}
