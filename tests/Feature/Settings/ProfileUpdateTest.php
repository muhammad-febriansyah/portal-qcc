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
            ->missing('profile.availability_status'));
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
            'phone_number' => '081234567890',
            'organization_id' => $organization->id,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->position)->toBe('Staff Operasional');
    expect($user->work_location)->toBe('Jakarta');
    expect($user->phone_number)->toBe('081234567890');
    expect($user->organization_id)->toBe($organization->id);
    expect($user->email_verified_at)->toBeNull();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('profile.phone_number', '081234567890')
            ->missing('profile.work_contact')
            ->missing('profile.personal_contact'));
});

test('the mobile number is required, must look like a phone number and stays unchanged after invalid input', function () {
    $user = User::factory()->create(['phone_number' => '08111112222']);
    $organization = Organization::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'position' => 'Staff Operasional',
            'work_location' => 'Jakarta',
            'organization_id' => $organization->id,
        ])
        ->assertSessionHasErrors(['phone_number' => 'Nomor handphone wajib diisi.']);

    foreach (['0812-abc-999', '12345', '0812 3456 7890 1234 5678'] as $invalidNumber) {
        $this->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'position' => 'Staff Operasional',
            'work_location' => 'Jakarta',
            'organization_id' => $organization->id,
            'phone_number' => $invalidNumber,
        ])->assertSessionHasErrors('phone_number');
    }

    expect($user->fresh()->phone_number)->toBe('08111112222');
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
            'phone_number' => '081234567891',
            'organization_id' => $organization->id,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('saving the profile keeps the stored availability', function () {
    $user = User::factory()->create(['availability_status' => 'UNAVAILABLE']);
    $organization = Organization::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'position' => 'Staff Operasional',
            'work_location' => 'HO Kebagusan',
            'phone_number' => '081234567892',
            'organization_id' => $organization->id,
            'availability_status' => 'AVAILABLE',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->availability_status)->toBe('UNAVAILABLE');
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
            'phone_number' => '081234567893',
            'organization_id' => $organization->id,
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
