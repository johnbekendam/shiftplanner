<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $url, public string $purpose) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __("auth.mail_subject_{$this->purpose}"));
    }

    public function content(): Content
    {
        return new Content(text: 'emails.login-link', with: ['purpose' => $this->purpose]);
    }
}
