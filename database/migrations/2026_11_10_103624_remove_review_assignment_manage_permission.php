<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Reviewers come from the organization's Div/LOB head now, so nobody assigns them by hand
     * and the permission for it goes. Existing assignments stay and keep working.
     */
    public function up(): void
    {
        Permission::query()->where('name', 'review-assignments.manage')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Give the permission back to Owner QCC.
     */
    public function down(): void
    {
        $permission = Permission::findOrCreate('review-assignments.manage', 'web');

        config('permission.models.role')::query()->where('name', 'Owner QCC')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
