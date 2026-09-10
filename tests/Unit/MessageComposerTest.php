<?php

namespace Tests\Unit;

use App\Services\MessageComposer;
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

    public function test_render_for_recipient_wraps_in_branded_layout(): void
    {
        $result = (new MessageComposer)->renderForRecipient('Hello', 'Some *body* text');

        $this->assertSame('Hello', $result['subject']);
        $this->assertStringContainsString('<em', $result['html']);
        $this->assertStringContainsString('body', $result['html']);
        $this->assertStringContainsStringIgnoringCase('<!DOCTYPE html>', $result['html']);
        $this->assertStringContainsString(config('app.name'), $result['html']);
        $this->assertStringContainsString('src="'.asset('images/logo.svg').'"', $result['html']);
        $this->assertStringContainsString('alt="'.config('app.name').'"', $result['html']);
    }
}
