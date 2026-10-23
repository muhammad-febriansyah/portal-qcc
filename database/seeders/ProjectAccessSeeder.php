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
        'Owner QCC' => ['projects.view', 'projects.viewAny', 'projects.review', 'projects.approve', 'evidence.view', 'review-assignments.manage', 'competencies.verify'],
        'Mentor' => ['projects.view', 'projects.review', 'projects.approve'],
        'Team Leader' => ['projects.view', 'projects.create', 'projects.update', 'projects.submit', 'evidence.view', 'evidence.upload'],
        'SIMer / Member' => ['projects.view', 'projects.create', 'projects.update', 'projects.submit', 'evidence.view', 'evidence.upload'],
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

        Role::findOrCreate('Super Admin', 'web')->revokePermissionTo([
            Permission::findOrCreate('projects.review', 'web'),
            Permission::findOrCreate('projects.approve', 'web'),
        ]);
    }
}
