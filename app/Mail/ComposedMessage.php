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
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class ComposedMessage extends Mailable
{
    use InlinesEmailStyles, Queueable, SerializesModels;

    public const LOGO_CONTENT_ID = 'shiftplanner-logo@shiftplanner.local';

    public function __construct(
        private string $renderedSubject,
        public string $bodyHtml,
        private ?string $replyToEmail = null,
        private ?string $replyToName = null,
        private ?string $logoSrc = null,
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
                'logoSrc' => $this->logoSrc ?? 'cid:'.self::LOGO_CONTENT_ID,
            ],
        );
    }

    public static function browserLogoUrl(): string
    {
        return asset('images/'.(self::logoFilename('filename') ?? 'logo.svg'));
    }

    protected function buildAttachments($message): void
    {
        parent::buildAttachments($message);

        /** @var Email $email */
        $email = $message->getSymfonyMessage();
        $path = self::emailLogoPath();
        $logo = DataPart::fromPath($path, basename($path), self::logoContentType($path));
        $email->addPart($logo->setContentId(self::LOGO_CONTENT_ID));
    }

    private static function emailLogoPath(): string
    {
        $filename = self::logoFilename('email_filename')
            ?? self::logoFilename('filename')
            ?? 'logo.svg';

        return public_path("images/{$filename}");
    }

    private static function logoFilename(string $key): ?string
    {
        if (! Storage::exists('logo.json')) {
            return null;
        }

        $data = json_decode(Storage::get('logo.json'), true);
        $filename = is_array($data) ? ($data[$key] ?? null) : null;

        return $filename && file_exists(public_path("images/{$filename}")) ? $filename : null;
    }

    private static function logoContentType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/svg+xml',
        };
    }
}
