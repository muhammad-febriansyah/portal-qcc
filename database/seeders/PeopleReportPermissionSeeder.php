<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PeopleReportPermissionSeeder extends Seeder
{
    /**
     * Who may look people up by competency. Super Admin and Owner QCC only.
     * Idempotent.
     *
     * @var list<string>
     */
    private const ROLES = ['Super Admin', 'Owner QCC'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('people.report', 'web');

        foreach (self::ROLES as $roleName) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo($permission);
        }
    }
}
