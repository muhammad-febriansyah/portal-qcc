<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DashboardPermissionSeeder extends Seeder
{
    /**
     * Super Admin sees the system overview on the dashboard. Idempotent.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('Super Admin', 'web')
            ->givePermissionTo(Permission::findOrCreate('dashboard.admin', 'web'));
    }
}
