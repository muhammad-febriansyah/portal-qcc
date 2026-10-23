<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\ProjectAccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(ProjectAccessSeeder::class);
});

test('a newly registered member is sent to the profile page from other menus', function () {
    $user = User::factory()->create(['needs_profile_setup' => true]);
    $user->assignRole('SIMer / Member');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('profile.edit'));

    $this->actingAs($user)
        ->get(route('proposals.index'))
        ->assertRedirect(route('profile.edit'));
});

test('the profile page stays open and asks the member to complete it', function () {
    $user = User::factory()->create(['needs_profile_setup' => true]);
    $user->assignRole('SIMer / Member');

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('profileComplete', false));
});

test('completing the profile opens the menus and goes to the dashboard', function () {
    $user = User::factory()->create(['needs_profile_setup' => true]);
    $user->assignRole('SIMer / Member');
    $organization = Organization::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'position' => 'Staf Operasional',
            'work_location' => 'Jakarta',
            'organization_id' => $organization->id,
            'availability_status' => 'AVAILABLE',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertOk();
});

test('saving a still incomplete profile keeps the member on the profile page', function () {
    $user = User::factory()->create(['needs_profile_setup' => true]);
    $user->assignRole('SIMer / Member');

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Nama Baru',
            'email' => $user->email,
            'position' => '',
            'work_location' => '',
            'availability_status' => '',
        ])
        ->assertSessionHasErrors(['position', 'work_location', 'availability_status']);

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('profile.edit'));
});

test('accounts that were not self registered are never held back', function () {
    $user = User::factory()->create();
    $user->assignRole('SIMer / Member');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});
