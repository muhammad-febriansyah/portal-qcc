<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ScoringPermissionSeeder extends Seeder
{
    /**
     * Who may do what with AI scores (docs/06 matrix). Mentor, Team Leader and
     * Member get none by default. Idempotent.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'Owner QCC' => ['scoring.view', 'scoring.run', 'scoring.review', 'scoring.approve'],
        'Super Admin' => ['scoring.view', 'scoring.run'],
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
