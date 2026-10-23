<?php

namespace App\Mail;

use App\Mail\Concerns\HasMailBranding;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class VerifyEmailMail extends Mailable
{
    use HasMailBranding, Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly string $verificationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verifikasi email akun '.$this->appName());
    }

    public function content(): Content
    {
        $minutes = (int) config('auth.verification.expire', 60);
        $expiresAt = Carbon::now($this->timezone())->addMinutes($minutes);

        $rows = ['Akun' => $this->recipient->email];

        if (filled($this->recipient->sim_id)) {
            $rows['SIM ID'] = (string) $this->recipient->sim_id;
        }

        $rows['Berlaku sampai'] = $expiresAt->translatedFormat('j F Y, H.i T')." ({$minutes} menit)";

        return new Content(
            view: 'mail.verify-email',
            text: 'mail.verify-email-text',
            with: [
                ...$this->brandData(),
                'recipientName' => $this->recipient->name,
                'rows' => $rows,
                'actionUrl' => $this->verificationUrl,
            ],
        );
    }
}
