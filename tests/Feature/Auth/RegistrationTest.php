<?php

use App\Models\User;
use Database\Seeders\ProjectAccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Sari Wulandari',
        'sim_id' => 'sim12345',
        'email' => 'Sari.Wulandari@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$overrides,
    ];
}

test('registration screen is displayed', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/register'));
});

test('login screen offers the registration link', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('canRegister', true));
});

test('new users register as SIMer / Member and are signed in', function () {
    $this->seed(ProjectAccessSeeder::class);

    $response = $this->post(route('register.store'), registrationPayload());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('sim_id', 'SIM12345')->firstOrFail();

    expect($user->name)->toBe('Sari Wulandari')
        ->and($user->email)->toBe('sari.wulandari@example.test')
        ->and($user->is_active)->toBeTrue()
        ->and($user->organization_id)->toBeNull()
        ->and($user->mustCompleteProfile())->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->getRoleNames()->all())->toBe(['SIMer / Member']);
});

test('registration cannot choose a role', function () {
    $this->seed(ProjectAccessSeeder::class);

    $this->post(route('register.store'), registrationPayload(['role' => 'Super Admin']));

    $user = User::query()->where('sim_id', 'SIM12345')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe(['SIMer / Member']);
});

test('registration rejects an email or SIM ID that is already used', function () {
    User::factory()->create(['email' => 'sari.wulandari@example.test', 'sim_id' => 'SIM12345']);

    $this->post(route('register.store'), registrationPayload())
        ->assertSessionHasErrors([
            'email' => 'Email sudah terdaftar.',
            'sim_id' => 'SIM ID sudah terdaftar.',
        ]);

    $this->assertGuest();
});

test('registration rejects a SIM ID with spaces or symbols', function () {
    $this->post(route('register.store'), registrationPayload(['sim_id' => 'SIM 123!']))
        ->assertSessionHasErrors('sim_id');

    $this->assertGuest();
});

test('registration rejects a password that is not confirmed', function () {
    $this->post(route('register.store'), registrationPayload(['password_confirmation' => 'different']))
        ->assertSessionHasErrors('password');

    $this->assertGuest();
});

test('registration requires name, SIM ID, email and password', function () {
    $this->post(route('register.store'), [])
        ->assertSessionHasErrors(['name', 'sim_id', 'email', 'password']);
});
