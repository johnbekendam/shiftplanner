<?php

namespace App\Mail;

use App\Mail\Concerns\InlinesEmailStyles;
use App\Services\ThemeTokens;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComposedMessage extends Mailable
{
    use InlinesEmailStyles, Queueable, SerializesModels;

    public function __construct(
        private string $renderedSubject,
        public string $bodyHtml,
        private ?string $replyToEmail = null,
        private ?string $replyToName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
            replyTo: $this->replyToEmail ? [new Address($this->replyToEmail, $this->replyToName)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.message',
            with: ['colors' => (new ThemeTokens)->emailColors()],
        );
    }
}
