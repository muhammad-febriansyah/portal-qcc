<?php

use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $user = User::factory()->create();
    $activeOrganization = Organization::factory()->create(['is_active' => true]);
    Organization::factory()->create(['is_active' => false]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('organizations', [['id' => $activeOrganization->id, 'name' => $activeOrganization->name]])
            ->where('profile.availability_status', null));
});

test('profile information can be updated', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['is_active' => true]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'position' => 'Staff Operasional',
            'work_location' => 'Jakarta',
            'work_contact' => '0215550101',
            'personal_contact' => '081234567890',
            'organization_id' => $organization->id,
            'availability_status' => 'AVAILABLE',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->position)->toBe('Staff Operasional');
    expect($user->work_location)->toBe('Jakarta');
    expect($user->work_contact)->toBe('0215550101');
    expect($user->personal_contact)->toBe('081234567890');
    expect($user->organization_id)->toBe($organization->id);
    expect($user->availability_status)->toBe('AVAILABLE');
    expect($user->email_verified_at)->toBeNull();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('profile.work_contact', '0215550101')
            ->where('profile.personal_contact', '081234567890'));
});

test('work and personal contacts are required and existing contacts stay unchanged after invalid input', function () {
    $user = User::factory()->create([
        'work_contact' => '02111112222',
        'personal_contact' => '08111112222',
    ]);
    $organization = Organization::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'position' => 'Staff Operasional',
            'work_location' => 'Jakarta',
            'organization_id' => $organization->id,
            'availability_status' => 'AVAILABLE',
        ])
        ->assertSessionHasErrors(['work_contact', 'personal_contact']);

    expect($user->fresh()->work_contact)->toBe('02111112222')
        ->and($user->fresh()->personal_contact)->toBe('08111112222');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['is_active' => true]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
            'position' => 'Staff Operasional',
            'work_location' => 'Jakarta',
            'work_contact' => '0215550102',
            'personal_contact' => '081234567891',
            'organization_id' => $organization->id,
            'availability_status' => 'UNAVAILABLE',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
    expect($user->availability_status)->toBe('UNAVAILABLE');
});

test('unsupported availability statuses are rejected', function () {
    $user = User::factory()->create(['availability_status' => 'AVAILABLE']);
    $organization = Organization::factory()->create(['is_active' => true]);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'position' => $user->position ?? 'Staff Operasional',
            'work_location' => $user->work_location ?? 'Jakarta',
            'work_contact' => $user->work_contact ?? '0215550103',
            'personal_contact' => $user->personal_contact ?? '081234567892',
            'organization_id' => $organization->id,
            'availability_status' => 'MAYBE',
        ]);

    $response->assertSessionHasErrors('availability_status');

    expect($user->refresh()->availability_status)->toBe('AVAILABLE');
});

test('inactive organizations cannot be selected in a profile', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['is_active' => false]);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'position' => 'Staff Operasional',
            'work_location' => 'Jakarta',
            'work_contact' => '0215550104',
            'personal_contact' => '081234567893',
            'organization_id' => $organization->id,
            'availability_status' => 'AVAILABLE',
        ]);

    $response->assertSessionHasErrors('organization_id');

    expect($user->refresh()->organization_id)->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
