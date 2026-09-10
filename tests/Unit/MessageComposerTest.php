<?php

namespace Tests\Unit;

use App\Mail\ComposedMessage;
use App\Services\MessageComposer;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MessageComposerTest extends TestCase
{
    public function test_render_converts_markdown_to_html(): void
    {
        $result = (new MessageComposer)->render('Hello', "**bold** text\n\nnew paragraph");

        $this->assertSame('Hello', $result['subject']);
        $this->assertStringContainsString('<strong>bold</strong>', $result['body_html']);
        $this->assertStringContainsString('<p>', $result['body_html']);
    }

    public function test_render_converts_button_syntax_to_a_branded_email_button(): void
    {
        $result = (new MessageComposer)->render('Hello', ':button[Open schedule](https://example.test/schedule)');

        $this->assertStringContainsString('href="https://example.test/schedule"', $result['body_html']);
        $this->assertStringContainsString('Open schedule', $result['body_html']);
        $this->assertStringContainsString('role="presentation"', $result['body_html']);
        $this->assertStringNotContainsString(':button', $result['body_html']);
    }

    public function test_render_keeps_regular_markdown_links_as_links(): void
    {
        $result = (new MessageComposer)->render('Hello', '[Open schedule](https://example.test/schedule)');

        $this->assertStringContainsString('<a href="https://example.test/schedule">Open schedule</a>', $result['body_html']);
        $this->assertStringNotContainsString('role="presentation"', $result['body_html']);
    }

    public function test_render_for_recipient_wraps_in_branded_layout(): void
    {
        $result = (new MessageComposer)->renderForRecipient('Hello', 'Some *body* text');

        $this->assertSame('Hello', $result['subject']);
        $this->assertStringContainsString('<em', $result['html']);
        $this->assertStringContainsString('body', $result['html']);
        $this->assertStringContainsStringIgnoringCase('<!DOCTYPE html>', $result['html']);
        $this->assertStringContainsString(config('app.name'), $result['html']);
        $this->assertStringContainsString('src="'.asset('images/logo.svg').'"', $result['html']);
        $this->assertStringContainsString('width="112"', $result['html']);
        $this->assertStringContainsString('width:112px;height:auto;', $result['html']);
        $this->assertStringNotContainsString('height="22"', $result['html']);
        $this->assertStringContainsString('alt="'.config('app.name').'"', $result['html']);
    }

    public function test_composed_message_embeds_the_email_logo_when_available(): void
    {
        $emailLogoPath = public_path('images/logo-custom-email.png');
        $originalLogoSettings = Storage::exists('logo.json') ? Storage::get('logo.json') : null;

        file_put_contents(public_path('images/logo-custom.svg'), '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        file_put_contents($emailLogoPath, 'email-logo');
        Storage::put('logo.json', json_encode([
            'filename' => 'logo-custom.svg',
            'email_filename' => 'logo-custom-email.png',
        ]));

        try {
            $email = new Email;
            $message = new class($email) {
                public function __construct(private Email $email) {}

                public function getSymfonyMessage(): Email
                {
                    return $this->email;
                }
            };

            $callBuildAttachments = function ($message): void {
                $this->buildAttachments($message);
            };

            $callBuildAttachments->call(new ComposedMessage('Hello', '<p>Body</p>'), $message);
            $attachment = $email->getAttachments()[0];

            $this->assertSame('logo-custom-email.png', $attachment->getName());
            $this->assertSame('image/png', $attachment->getContentType());
            $this->assertSame(ComposedMessage::LOGO_CONTENT_ID, $attachment->getContentId());
            $this->assertSame('email-logo', $attachment->getBody());
        } finally {
            @unlink(public_path('images/logo-custom.svg'));
            @unlink($emailLogoPath);
            if ($originalLogoSettings === null) {
                Storage::delete('logo.json');
            } else {
                Storage::put('logo.json', $originalLogoSettings);
            }
        }
    }
}
