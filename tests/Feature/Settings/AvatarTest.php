<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
});

test('guests cannot change an avatar', function () {
    $this->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('a.png')])
        ->assertRedirect(route('login'));
});

test('a user can upload a profile photo and it is exposed as a url', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('foto.png', 300, 300);

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), ['avatar' => $file])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user->refresh();

    Storage::disk('public')->assertExists($user->avatar_path);
    expect($user->avatar_path)->toStartWith('avatars/')
        ->and($user->avatar)->toBe('/storage/'.$user->avatar_path);

    $this->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.avatar', '/storage/'.$user->avatar_path)
            ->missing('auth.user.avatar_path'));
});

test('a new photo replaces and deletes the old file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('satu.jpg')]);
    $first = $user->fresh()->avatar_path;

    $this->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('dua.webp')]);
    $second = $user->fresh()->avatar_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

test('only small jpg png or webp photos are accepted', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->post(route('profile.avatar.update'), [])->assertSessionHasErrors('avatar');
    $this->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])
        ->assertSessionHasErrors('avatar');
    $this->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml')])
        ->assertSessionHasErrors('avatar');
    $this->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('besar.png')->size(3000)])
        ->assertSessionHasErrors('avatar');
    $this->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('lebar.png', 5000, 100)])
        ->assertSessionHasErrors('avatar');

    expect($user->fresh()->avatar_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('a user can remove their photo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('foto.png')]);
    $path = $user->fresh()->avatar_path;

    $this->delete(route('profile.avatar.destroy'))->assertRedirect();

    expect($user->fresh()->avatar_path)->toBeNull()
        ->and($user->fresh()->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('removing when there is no photo does nothing harmful', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.avatar.destroy'))->assertRedirect();

    expect(AuditLog::query()->where('event', 'user.avatar_removed')->count())->toBe(0);
});

test('photo changes are audited without exposing the file path', function () {
    $user = User::factory()->create(['name' => 'Rina Foto']);

    $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('foto.png')]);
    $this->delete(route('profile.avatar.destroy'));

    expect(AuditLog::query()->where('event', 'user.avatar_changed')->where('actor_id', $user->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'user.avatar_removed')->exists())->toBeTrue()
        ->and(AuditLog::query()->get()->toJson())->not->toContain('avatars/');
});

test('the change password page is shown without asking to confirm the password first', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('user-password.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/password')
            ->has('passwordRules'));
});

test('guests are redirected from the change password page', function () {
    $this->get(route('user-password.edit'))->assertRedirect(route('login'));
});

test('password change errors are in Indonesian', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('user-password.update'), [
            'current_password' => 'salah-total',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'beda',
        ])
        ->assertSessionHasErrors([
            'current_password' => 'Password saat ini tidak sesuai.',
            'password' => 'Konfirmasi password tidak sama.',
        ]);
});
