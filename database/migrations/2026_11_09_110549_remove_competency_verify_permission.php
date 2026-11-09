<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Competency verification is gone, so its permission goes too. Stored verified levels
     * stay in member_competencies but are no longer read anywhere.
     */
    public function up(): void
    {
        Permission::query()->where('name', 'competencies.verify')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Give the permission back to the roles that had it.
     */
    public function down(): void
    {
        $permission = Permission::findOrCreate('competencies.verify', 'web');

        foreach (['Super Admin', 'Owner QCC'] as $roleName) {
            $role = config('permission.models.role')::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
