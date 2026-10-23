<?php

use App\Models\Organization;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Database\Seeders\ProjectAccessSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mime\Email;

/**
 * @return list<Email>
 */
function verificationEmails(): array
{
    $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();

    return array_map(fn ($sent) => $sent->getOriginalMessage(), $transport->messages()->all());
}

/**
 * @return array<string, mixed>
 */
function profilePayload(string $email, string $name = 'Sari Wulandari'): array
{
    return [
        'name' => $name,
        'email' => $email,
        'position' => 'Staf Operasional',
        'work_location' => 'Jakarta',
        'organization_id' => Organization::factory()->create(['is_active' => true])->id,
        'availability_status' => 'AVAILABLE',
    ];
}

function verificationLink(User $user, int $minutes = 60): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);
}

test('registering sends the verification notification to the new address', function () {
    Notification::fake();
    $this->seed(ProjectAccessSeeder::class);

    $this->post(route('register.store'), [
        'name' => 'Sari Wulandari',
        'sim_id' => 'SIM12345',
        'email' => 'sari@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'sari@example.test')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('the verification email has the logo, the account details and a signed link', function () {
    $user = User::factory()->unverified()->create(['name' => 'Sari Wulandari', 'email' => 'sari@example.test', 'sim_id' => 'SIM12345']);

    $user->sendEmailVerificationNotification();

    $emails = verificationEmails();
    expect($emails)->toHaveCount(1);

    $html = (string) $emails[0]->getHtmlBody();
    $text = (string) $emails[0]->getTextBody();

    expect($emails[0]->getSubject())->toStartWith('Verifikasi email akun')
        ->and($emails[0]->getTo()[0]->getAddress())->toBe('sari@example.test')
        ->and($html)->toContain('Halo Sari Wulandari,')
        ->and($html)->toContain('Verifikasi email Anda')
        ->and($html)->toContain('sari@example.test')
        ->and($html)->toContain('SIM12345')
        ->and($html)->toContain('src="cid:')
        ->and($html)->toContain('/email/verify/'.$user->id.'/')
        ->and($html)->toContain('signature=')
        ->and($text)->toContain('/email/verify/'.$user->id.'/')
        ->and($emails[0]->getAttachments())->toHaveCount(1);
});

test('the verification email is sent even when notification emails are switched off', function () {
    config(['app.notifications.channels.email' => false]);
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    expect(verificationEmails())->toHaveCount(1);
});

test('an unverified account is sent to the verification page instead of the portal', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('the verification page shows the status after a resend', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/verify-email'));
});

test('a verified account is not shown the verification page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertRedirect(route('dashboard', absolute: false));
});

test('the signed link verifies the account and opens the portal', function () {
    Event::fake([Verified::class]);
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(verificationLink($user))
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

test('a link with a wrong hash does not verify the account', function () {
    $user = User::factory()->unverified()->create();
    $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1('someone-else@example.test')]);

    $this->actingAs($user)->get($link)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an expired link does not verify the account', function () {
    $user = User::factory()->unverified()->create();
    $link = verificationLink($user, minutes: -5);

    $this->actingAs($user)->get($link)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a link meant for another account cannot be used by someone else', function () {
    $owner = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();

    $this->actingAs($other)->get(verificationLink($owner))->assertForbidden();

    expect($owner->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and($other->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('the verification email can be sent again', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.send'))
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('a guest cannot request or use a verification link', function () {
    $user = User::factory()->unverified()->create();

    $this->post(route('verification.send'))->assertRedirect(route('login'));
    $this->get(verificationLink($user))->assertRedirect(route('login'));
});

test('changing the email address asks for a new verification', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.test']);

    $this->actingAs($user)
        ->patch(route('profile.update'), profilePayload('new@example.test'))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user->fresh(), VerifyEmailNotification::class);
});

test('saving the profile without changing the email does not send a verification', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'same@example.test']);

    $this->actingAs($user)
        ->patch(route('profile.update'), profilePayload('same@example.test', 'Nama Baru'))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Notification::assertNothingSent();
});
