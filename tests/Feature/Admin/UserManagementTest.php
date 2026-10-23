<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function userManager(): User
{
    $role = Role::findOrCreate('Super Admin', 'web');
    $role->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function validUserPayload(array $overrides = []): array
{
    return [
        'name' => 'Budi Santoso',
        'sim_id' => 'SIM-0001',
        'email' => 'budi@example.com',
        'organization_id' => Organization::factory()->create(['is_active' => true])->id,
        'role' => 'SIMer / Member',
        ...$overrides,
    ];
}

beforeEach(function () {
    foreach (['Super Admin', 'Owner QCC', 'Mentor', 'Team Leader', 'SIMer / Member'] as $name) {
        Role::findOrCreate($name, 'web');
    }
});

test('guests are redirected to login', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('users without users.manage cannot use any user endpoint', function () {
    $owner = User::factory()->create();
    $owner->assignRole('Owner QCC');
    $target = User::factory()->create();

    $this->actingAs($owner);

    $this->get(route('admin.users.index'))->assertForbidden();
    $this->post(route('admin.users.store'), validUserPayload())->assertForbidden();
    $this->put(route('admin.users.update', $target), validUserPayload())->assertForbidden();
    $this->put(route('admin.users.password.update', $target))->assertForbidden();
    $this->patch(route('admin.users.status.update', $target), ['is_active' => false])->assertForbidden();
    $this->patch(route('admin.users.bulk-status.update'), ['user_ids' => [$target->id], 'is_active' => false])->assertForbidden();
    $this->delete(route('admin.users.bulk.destroy'), ['user_ids' => [$target->id]])->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();
});

test('the user list is paginated and never exposes secrets', function () {
    User::factory()->count(12)->create();

    $this->actingAs(userManager())
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 10)
            ->where('users.total', 13)
            ->has('roleOptions', 5)
            ->missing('users.data.0.password')
            ->missing('users.data.0.remember_token'));
});

test('the user list can be searched and filtered by role and status', function () {
    $manager = userManager();
    $mentor = User::factory()->create(['name' => 'Maya Mentor', 'email' => 'maya@example.com', 'sim_id' => 'SIM-777']);
    $mentor->assignRole('Mentor');
    $inactive = User::factory()->create(['name' => 'Ina Nonaktif']);
    $inactive->forceFill(['is_active' => false])->save();

    $this->actingAs($manager);

    $this->get(route('admin.users.index', ['search' => 'maya@']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Maya Mentor'));

    $this->get(route('admin.users.index', ['search' => 'SIM-777']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.sim_id', 'SIM-777')
            ->where('users.data.0.role', 'Mentor'));

    $this->get(route('admin.users.index', ['role' => 'Mentor']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1));

    $this->get(route('admin.users.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Ina Nonaktif'));
});

test('the user list rejects unknown sort columns and roles', function () {
    $this->actingAs(userManager());

    $this->get(route('admin.users.index', ['sort' => 'password']))->assertSessionHasErrors('sort');
    $this->get(route('admin.users.index', ['role' => 'Ghost']))->assertSessionHasErrors('role');
});

test('the user list loads organizations and roles without extra queries per row', function () {
    User::factory()->count(10)->create(['organization_id' => Organization::factory()->create()->id]);
    $this->actingAs(userManager());

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get(route('admin.users.index', ['per_page' => 10]))->assertOk();

    expect($queries)->toBeLessThan(20);
});

test('creating a member stores a verified active account with a one-time password', function () {
    $payload = validUserPayload(['email' => ' Budi@Example.com ', 'sim_id' => ' sim-0042 ', 'role' => 'Mentor']);

    $this->actingAs(userManager())
        ->post(route('admin.users.store'), $payload)
        ->assertRedirect()
        ->assertInertiaFlash('credential.email', 'budi@example.com')
        ->assertInertiaFlash('credential.reason', 'created');

    $user = User::query()->where('email', 'budi@example.com')->firstOrFail();

    expect($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->organization_id)->toBe($payload['organization_id'])
        ->and($user->sim_id)->toBe('SIM-0042')
        ->and($user->getRoleNames()->all())->toBe(['Mentor']);
});

test('the generated password is hashed and lets the new member log in', function () {
    $response = $this->actingAs(userManager())->post(route('admin.users.store'), validUserPayload());
    $password = session('inertia.flash_data.credential.password');
    $user = User::query()->where('email', 'budi@example.com')->firstOrFail();

    expect($password)->toBeString()->toHaveLength(12)
        ->and($user->password)->not->toBe($password)
        ->and(Hash::check($password, $user->password))->toBeTrue();

    auth()->logout();

    $this->post(route('login.store'), ['email' => 'budi@example.com', 'password' => $password])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('creating a member validates its fields', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $inactiveOrganization = Organization::factory()->create(['is_active' => false]);

    $this->actingAs(userManager());

    $this->post(route('admin.users.store'), [])
        ->assertSessionHasErrors(['name', 'sim_id', 'email', 'organization_id', 'role']);
    $this->post(route('admin.users.store'), validUserPayload(['email' => 'taken@example.com']))
        ->assertSessionHasErrors('email');
    $this->post(route('admin.users.store'), validUserPayload(['email' => 'not-an-email']))
        ->assertSessionHasErrors('email');
    $this->post(route('admin.users.store'), validUserPayload(['organization_id' => $inactiveOrganization->id]))
        ->assertSessionHasErrors('organization_id');
    $this->post(route('admin.users.store'), validUserPayload(['role' => '']))
        ->assertSessionHasErrors('role');
    $this->post(route('admin.users.store'), validUserPayload(['role' => 'Hacker']))
        ->assertSessionHasErrors('role');
    $this->post(route('admin.users.store'), validUserPayload(['role' => ['Mentor', 'Team Leader']]))
        ->assertSessionHasErrors('role');
    $this->post(route('admin.users.store'), validUserPayload(['sim_id' => 'SIM-TAKEN']))
        ->assertSessionHasNoErrors();
    $this->post(route('admin.users.store'), validUserPayload(['email' => 'second@example.com', 'sim_id' => 'sim-taken']))
        ->assertSessionHasErrors('sim_id');
    $this->post(route('admin.users.store'), validUserPayload(['email' => 'third@example.com', 'sim_id' => 'bad id!']))
        ->assertSessionHasErrors('sim_id');
});

test('updating a member changes identity, organization and roles but not the password', function () {
    $target = User::factory()->create(['email' => 'old@example.com']);
    $target->assignRole('Mentor');
    $originalHash = $target->password;

    $this->actingAs(userManager())
        ->put(route('admin.users.update', $target), validUserPayload([
            'name' => 'Nama Baru',
            'email' => 'old@example.com',
            'role' => 'Team Leader',
        ]))
        ->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->name)->toBe('Nama Baru')
        ->and($target->getRoleNames()->all())->toBe(['Team Leader'])
        ->and($target->password)->toBe($originalHash);
});

test('a member cannot take the SIM ID of another member but may keep their own', function () {
    User::factory()->create(['sim_id' => 'SIM-OTHER']);
    $target = User::factory()->create(['sim_id' => 'SIM-MINE']);

    $this->actingAs(userManager());

    $this->put(route('admin.users.update', $target), validUserPayload(['email' => $target->email, 'sim_id' => 'SIM-OTHER']))
        ->assertSessionHasErrors('sim_id');
    $this->put(route('admin.users.update', $target), validUserPayload(['email' => $target->email, 'sim_id' => 'SIM-MINE']))
        ->assertSessionHasNoErrors();
});

test('a member cannot take another member email', function () {
    User::factory()->create(['email' => 'other@example.com']);
    $target = User::factory()->create();

    $this->actingAs(userManager())
        ->put(route('admin.users.update', $target), validUserPayload(['email' => 'other@example.com']))
        ->assertSessionHasErrors('email');
});

test('a member may keep an organization that was deactivated later but cannot move to one', function () {
    $oldOrganization = Organization::factory()->create(['is_active' => false]);
    $target = User::factory()->create(['organization_id' => $oldOrganization->id]);
    $otherInactive = Organization::factory()->create(['is_active' => false]);

    $this->actingAs(userManager());

    $this->put(route('admin.users.update', $target), validUserPayload([
        'email' => $target->email,
        'organization_id' => $oldOrganization->id,
    ]))->assertSessionHasNoErrors();

    $this->put(route('admin.users.update', $target), validUserPayload([
        'email' => $target->email,
        'organization_id' => $otherInactive->id,
    ]))->assertSessionHasErrors('organization_id');
});

test('the last active super admin cannot lose the super admin role', function () {
    $admin = userManager();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), validUserPayload([
            'email' => $admin->email,
            'role' => 'Mentor',
        ]))
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->hasRole('Super Admin'))->toBeTrue();
});

test('a super admin can lose the role when another active super admin exists', function () {
    $admin = userManager();
    $second = User::factory()->create();
    $second->assignRole('Super Admin');

    $this->actingAs($admin)
        ->put(route('admin.users.update', $second), validUserPayload([
            'email' => $second->email,
            'role' => 'Mentor',
        ]))
        ->assertSessionHasNoErrors();

    expect($second->fresh()->hasRole('Super Admin'))->toBeFalse();
});

test('resetting a password issues a new one that works and the old one stops working', function () {
    $target = User::factory()->create();
    $oldHash = $target->password;
    $newPassword = 'Replacement-Password-2026';

    $this->actingAs(userManager())
        ->put(route('admin.users.password.update', $target), [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Password berhasil direset.');

    expect($target->fresh()->password)->not->toBe($oldHash)
        ->and(Hash::check($newPassword, $target->fresh()->password))->toBeTrue()
        ->and(Hash::check('password', $target->fresh()->password))->toBeFalse();
});

test('a member can be deactivated and activated again', function () {
    $target = User::factory()->create();

    $this->actingAs(userManager());

    $this->patch(route('admin.users.status.update', $target), ['is_active' => false])->assertRedirect();
    expect($target->fresh()->is_active)->toBeFalse();

    $this->patch(route('admin.users.status.update', $target), ['is_active' => true])->assertRedirect();
    expect($target->fresh()->is_active)->toBeTrue();
});

test('selected accounts can be activated and deactivated together', function () {
    $manager = userManager();
    $activeUser = User::factory()->create();
    $inactiveUser = User::factory()->create(['is_active' => false]);

    $this->actingAs($manager)
        ->patch(route('admin.users.bulk-status.update'), [
            'user_ids' => [$activeUser->id, $inactiveUser->id],
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', '1 akun berhasil dinonaktifkan.');

    expect($activeUser->fresh()->is_active)->toBeFalse()
        ->and($inactiveUser->fresh()->is_active)->toBeFalse();

    $this->patch(route('admin.users.bulk-status.update'), [
        'user_ids' => [$activeUser->id, $inactiveUser->id],
        'is_active' => true,
    ])->assertRedirect();

    expect($activeUser->fresh()->is_active)->toBeTrue()
        ->and($inactiveUser->fresh()->is_active)->toBeTrue();
});

test('bulk status rejects duplicate account selections', function () {
    $manager = userManager();
    $target = User::factory()->create();

    $this->actingAs($manager)
        ->patch(route('admin.users.bulk-status.update'), [
            'user_ids' => [$target->id, $target->id],
            'is_active' => false,
        ])
        ->assertSessionHasErrors('user_ids.1');

    expect($target->fresh()->is_active)->toBeTrue();
});

test('bulk status preserves the last active super admin', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));
    $lastAdmin = User::factory()->create();
    $lastAdmin->assignRole('Super Admin');

    $this->actingAs($operator)
        ->patch(route('admin.users.bulk-status.update'), [
            'user_ids' => [$lastAdmin->id],
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Super Admin aktif terakhir tidak dapat dinonaktifkan.');

    expect($lastAdmin->fresh()->is_active)->toBeTrue();
});

test('bulk actions cannot deactivate or delete the current account', function () {
    $manager = userManager();

    $this->actingAs($manager)
        ->patch(route('admin.users.bulk-status.update'), [
            'user_ids' => [$manager->id],
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Akun Anda sendiri tidak dapat dinonaktifkan.');

    $this->delete(route('admin.users.bulk.destroy'), ['user_ids' => [$manager->id]])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Akun Anda sendiri tidak dapat dihapus.');

    expect($manager->fresh()->is_active)->toBeTrue();
});

test('bulk delete removes selected unused accounts', function () {
    $manager = userManager();
    $targets = User::factory()->count(2)->create();

    $this->actingAs($manager)
        ->delete(route('admin.users.bulk.destroy'), [
            'user_ids' => $targets->pluck('id')->all(),
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', '2 akun berhasil dihapus.');

    foreach ($targets as $target) {
        expect(User::query()->whereKey($target->id)->exists())->toBeFalse();
    }
});

test('bulk delete rolls back when a selected account is still linked to a project', function () {
    $manager = userManager();
    $unusedUser = User::factory()->create();
    $projectOwner = User::factory()->create();
    Project::factory()->create(['proposer_id' => $projectOwner->id]);

    $this->actingAs($manager)
        ->delete(route('admin.users.bulk.destroy'), [
            'user_ids' => [$unusedUser->id, $projectOwner->id],
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Sebagian akun masih terhubung ke proyek atau aktivitas. Tidak ada akun yang dihapus.');

    expect(User::query()->whereKey($unusedUser->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($projectOwner->id)->exists())->toBeTrue();
});

test('bulk delete preserves the last active super admin', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));
    $lastAdmin = User::factory()->create();
    $lastAdmin->assignRole('Super Admin');

    $this->actingAs($operator)
        ->delete(route('admin.users.bulk.destroy'), ['user_ids' => [$lastAdmin->id]])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Super Admin aktif terakhir tidak dapat dihapus.');

    expect(User::query()->whereKey($lastAdmin->id)->exists())->toBeTrue()
        ->and($lastAdmin->fresh()->is_active)->toBeTrue();
});

test('you cannot deactivate yourself or the last active super admin', function () {
    $admin = userManager();

    $this->actingAs($admin)
        ->patch(route('admin.users.status.update', $admin), ['is_active' => false])
        ->assertRedirect();

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('the last active super admin cannot be deactivated by another manager', function () {
    $lastAdmin = User::factory()->create();
    $lastAdmin->assignRole('Super Admin');

    $manager = User::factory()->create();
    $manager->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($manager)
        ->patch(route('admin.users.status.update', $lastAdmin), ['is_active' => false])
        ->assertRedirect();

    expect($lastAdmin->fresh()->is_active)->toBeTrue();
});

test('a deactivated account cannot log in', function () {
    $user = User::factory()->create(['email' => 'off@example.com']);
    $user->forceFill(['is_active' => false])->save();

    $this->post(route('login.store'), ['email' => 'off@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a logged in account is signed out once it is deactivated', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->forceFill(['is_active' => false])->save();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
