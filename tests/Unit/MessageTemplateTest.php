<?php

namespace Tests\Unit;

use App\Enums\MessageType;
use App\Models\MessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_type_seeds_the_row_from_the_language_file(): void
    {
        $this->assertDatabaseCount('message_templates', 0);

        $template = MessageTemplate::forType(MessageType::PersonalPageLink);

        $this->assertSame(MessageType::PersonalPageLink, $template->type);
        $this->assertSame(__('mailbox.type.personal_page_link.subject'), $template->subject);
        $this->assertSame(__('mailbox.type.personal_page_link.body'), $template->body);
        $this->assertDatabaseCount('message_templates', 1);
    }

    public function test_for_type_returns_the_stored_row_without_overwriting_edits(): void
    {
        MessageTemplate::forType(MessageType::PersonalPageLink)->update([
            'subject' => 'Edited subject',
            'body' => 'Edited body :link',
        ]);

        $template = MessageTemplate::forType(MessageType::PersonalPageLink);

        $this->assertSame('Edited subject', $template->subject);
        $this->assertSame('Edited body :link', $template->body);
        $this->assertDatabaseCount('message_templates', 1);
    }
}
