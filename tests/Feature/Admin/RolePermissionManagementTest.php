<?php

use App\Models\AuditLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function rolePermissionManager(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('roles.manage', 'web'));

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('admin.roles.index'))->assertRedirect(route('login'));
});

test('users without roles.manage cannot view or change role permissions', function () {
    $role = Role::findOrCreate('Mentor', 'web');

    $this->actingAs(User::factory()->create())
        ->get(route('admin.roles.index'))
        ->assertForbidden();

    $this->put(route('admin.roles.permissions.update', $role), [
        'permissions' => [],
    ])->assertForbidden();

    expect($role->fresh()->permissions)->toBeEmpty();
});

test('authorized admins see official roles and can edit grants without entering a reason', function () {
    $admin = rolePermissionManager();
    $role = Role::findOrCreate('Mentor', 'web');
    $permission = Permission::findOrCreate('evidence.view', 'web');

    $this->actingAs($admin)
        ->get(route('admin.roles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/roles/index')
            ->where('roles', fn ($roles): bool => collect($roles)->contains(fn (array $item): bool => $item['id'] === $role->id && $item['name'] === 'Mentor'
            ))
            ->where('permissionCatalog', fn ($permissions): bool => collect($permissions)->contains($permission->name)));

    $this->put(route('admin.roles.permissions.update', $role), [
        'permissions' => [$permission->name],
    ])->assertRedirect(route('admin.roles.index'));

    expect($role->fresh()->hasPermissionTo($permission))->toBeTrue();

    $log = AuditLog::query()->where('event', 'role.permissions_updated')->firstOrFail();

    expect($log->actor_id)->toBe($admin->id)
        ->and($log->subject_label)->toBe('Mentor')
        ->and($log->before_json)->toBe(['permissions' => []])
        ->and($log->after_json)->toBe(['permissions' => [$permission->name]])
        ->and($log->reason)->toBeNull();
});

test('super admin cannot remove its role management permission', function () {
    $adminRole = Role::findOrCreate('Super Admin', 'web');
    $adminRole->syncPermissions([
        Permission::findOrCreate('roles.manage', 'web'),
        Permission::findOrCreate('users.manage', 'web'),
    ]);

    $this->actingAs(rolePermissionManager())
        ->put(route('admin.roles.permissions.update', $adminRole), [
            'permissions' => ['users.manage'],
        ])
        ->assertSessionHasErrors('permissions');

    expect($adminRole->fresh()->hasPermissionTo('roles.manage'))->toBeTrue()
        ->and(AuditLog::query()->where('event', 'role.permissions_updated')->exists())->toBeFalse();
});

test('permission grants reject unknown permission names', function () {
    $role = Role::findOrCreate('Team Leader', 'web');

    $this->actingAs(rolePermissionManager())
        ->put(route('admin.roles.permissions.update', $role), [
            'permissions' => ['system.super-secret'],
        ])
        ->assertSessionHasErrors('permissions.0');

    expect($role->fresh()->permissions)->toBeEmpty()
        ->and(AuditLog::query()->where('event', 'role.permissions_updated')->exists())->toBeFalse();
});

test('role permission editor rejects rubric publishing grant for non Super Admin roles', function () {
    $role = Role::findOrCreate('Owner QCC', 'web');
    Permission::findOrCreate('rubrics.manage', 'web');
    Permission::findOrCreate('rubrics.publish', 'web');

    $this->actingAs(rolePermissionManager())
        ->put(route('admin.roles.permissions.update', $role), [
            'permissions' => ['rubrics.manage', 'rubrics.publish'],
        ])
        ->assertSessionHasErrors('permissions');

    expect($role->fresh()->hasPermissionTo('rubrics.publish'))->toBeFalse();
});

test('role permissions are not logged when submitted grants are unchanged', function () {
    $role = Role::findOrCreate('Owner QCC', 'web');
    $permission = Permission::findOrCreate('projects.view', 'web');
    $role->syncPermissions([$permission]);

    $this->actingAs(rolePermissionManager())
        ->put(route('admin.roles.permissions.update', $role), [
            'permissions' => [$permission->name],
        ])
        ->assertRedirect(route('admin.roles.index'));

    expect(AuditLog::query()->where('event', 'role.permissions_updated')->exists())->toBeFalse();
});
