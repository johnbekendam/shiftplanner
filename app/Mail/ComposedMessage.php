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
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class ComposedMessage extends Mailable
{
    use InlinesEmailStyles, Queueable, SerializesModels;

    public const LOGO_CONTENT_ID = 'shiftplanner-logo';

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
            with: [
                'colors' => (new ThemeTokens)->emailColors(),
                'logoContentId' => self::LOGO_CONTENT_ID,
            ],
        );
    }

    protected function buildAttachments($message): void
    {
        parent::buildAttachments($message);

        /** @var Email $email */
        $email = $message->getSymfonyMessage();
        $logo = DataPart::fromPath(public_path('images/logo.svg'), 'logo.svg', 'image/svg+xml');
        $email->addPart($logo->setContentId(self::LOGO_CONTENT_ID));
    }
}
