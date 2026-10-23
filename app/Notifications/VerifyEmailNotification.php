<?php

namespace App\Notifications;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Asks a new account to confirm its email address. It is always sent, whatever the
 * notification settings say, because the account cannot be used before it is confirmed.
 * It is queued so a slow mail server never holds up the sign up, and the signed link is
 * made when the email is sent, so its time limit starts then.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function toMail($notifiable): VerifyEmailMail
    {
        assert($notifiable instanceof User);

        return (new VerifyEmailMail($notifiable, $this->verificationUrl($notifiable)))
            ->to($notifiable->email, $notifiable->name);
    }
}
