<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditDescriber;
use Database\Seeders\ProjectAccessSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

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

    $user = User::query()->where('sim_id', '12345')->firstOrFail();

    expect($user->name)->toBe('Sari Wulandari')
        ->and($user->email)->toBe('sari.wulandari@example.test')
        ->and($user->is_active)->toBeTrue()
        ->and($user->organization_id)->toBeNull()
        ->and($user->mustCompleteProfile())->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->getRoleNames()->all())->toBe(['SIMer / Member']);
});

test('self registration is audited with its role and without credentials', function () {
    $this->seed(ProjectAccessSeeder::class);

    $this->post(route('register.store'), registrationPayload());

    $user = User::query()->where('sim_id', '12345')->firstOrFail();
    $entries = AuditLog::query()
        ->where('event', 'user.registered')
        ->where('subject_id', $user->id)
        ->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->subject_type)->toBe($user->getMorphClass())
        ->and($entries->first()->subject_label)->toBe($user->name)
        ->and($entries->first()->after_json)->toBe(['role' => 'SIMer / Member'])
        ->and($entries->first()->eventLabel())->toBe('Pendaftaran mandiri')
        ->and(app(AuditDescriber::class)->describe($entries->first()))->toContain('mendaftar sebagai SIMer / Member')
        ->and(json_encode($entries->first()->toArray()))->not->toContain('password');
});

test('registration is limited to five attempts per IP per hour', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('register.store'), [])->assertSessionHasErrors(['name', 'email']);
    }

    $this->post(route('register.store'), [])
        ->assertSessionHas('errors', fn ($errors): bool => $errors->getBag('default')->first('email') === 'Terlalu banyak percobaan pendaftaran. Silakan coba kembali dalam satu jam.');
});

test('registration cannot choose a role', function () {
    $this->seed(ProjectAccessSeeder::class);

    $this->post(route('register.store'), registrationPayload(['role' => 'Super Admin']));

    $user = User::query()->where('sim_id', '12345')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe(['SIMer / Member']);
});

test('registration rejects an email or SIM ID that is already used', function () {
    User::factory()->create(['email' => 'sari.wulandari@example.test', 'sim_id' => '12345']);

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

test('registration treats the same SIM ID in different letter case as already used', function () {
    User::factory()->create(['sim_id' => '12345']);

    $this->post(route('register.store'), registrationPayload(['sim_id' => 'sim12345']))
        ->assertSessionHasErrors(['sim_id' => 'SIM ID sudah terdaftar.']);

    expect(User::query()->where('email', 'sari.wulandari@example.test')->exists())->toBeFalse();
});

test('a SIM ID taken at the very same moment is reported as a validation error, not a server error', function (string $takenField) {
    $this->seed(ProjectAccessSeeder::class);

    // Someone else registers after our checks passed and before our account is saved.
    Validator::resolver(function ($translator, $data, $rules, $messages, $attributes) use ($takenField) {
        $validator = new Illuminate\Validation\Validator($translator, $data, $rules, $messages, $attributes);
        $validator->after(fn () => DB::table('users')->insertOrIgnore([
            'name' => 'Racer',
            'password' => 'not-used',
            'sim_id' => $takenField === 'sim_id' ? '12345' : '99999',
            'email' => $takenField === 'email' ? 'sari.wulandari@example.test' : 'racer@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return $validator;
    });

    try {
        $this->post(route('register.store'), registrationPayload())
            ->assertSessionHasErrors([$takenField => $takenField === 'sim_id' ? 'SIM ID sudah terdaftar.' : 'Email sudah terdaftar.']);
    } finally {
        Validator::resolver(fn (...$arguments) => new Illuminate\Validation\Validator(...$arguments));
    }

    $this->assertGuest();
    expect(User::query()->count())->toBe(1);
})->with(['sim_id', 'email']);
