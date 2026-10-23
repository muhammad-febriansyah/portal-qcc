<?php

namespace App\Mail;

use App\Mail\Concerns\HasMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SmtpTestMail extends Mailable
{
    use HasMailBranding, Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Uji SMTP Portal QCC',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.smtp-test',
            with: [
                ...$this->brandData(),
                'rows' => [
                    'Pengirim' => (string) config('mail.from.address'),
                    'Server SMTP' => (string) config('mail.mailers.smtp.host'),
                    'Waktu' => Carbon::now($this->timezone())->translatedFormat('j F Y, H.i T'),
                ],
            ],
        );
    }
}
