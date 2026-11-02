<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('sends the verification email right away, without waiting for a queue worker', function () {
    Queue::fake();

    $user = User::factory()->create(['email_verified_at' => null]);

    $user->sendEmailVerificationNotification();

    Queue::assertNothingPushed();

    $messages = Mail::mailer('array')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->getEnvelope()->getRecipients()[0]->getAddress())->toBe($user->email);
});
