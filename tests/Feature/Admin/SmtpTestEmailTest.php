<?php

use App\Mail\SmtpTestMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

function smtpSettingsManager(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('system-settings.manage', 'web'));

    return $user;
}

test('authorized admins can send a test email using configured mail transport', function () {
    Mail::fake();

    $this->actingAs(smtpSettingsManager())
        ->post(route('admin.settings.test-email'), ['email' => 'tester@example.test'])
        ->assertRedirect();

    Mail::assertSent(SmtpTestMail::class, fn (SmtpTestMail $mail): bool => $mail->hasTo('tester@example.test')
        && $mail->usesMailer('smtp'));
});

test('test email rejects invalid recipient addresses', function () {
    Mail::fake();

    $this->actingAs(smtpSettingsManager())
        ->post(route('admin.settings.test-email'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

test('users without system settings permission cannot send test email', function () {
    Mail::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.settings.test-email'), ['email' => 'tester@example.test'])
        ->assertForbidden();

    Mail::assertNothingSent();
});
