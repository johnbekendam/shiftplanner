<?php

namespace Tests\Unit;

use App\Jobs\SendMailboxMessage;
use App\Mail\ComposedMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendMailboxMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_sends_mail_and_marks_message_sent(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $message = Message::factory()->for($user)->outbox()->create();

        (new SendMailboxMessage($message->id, $message->recipient_email, new ComposedMessage('Hello', '<p>Body</p>')))->handle();

        Mail::assertSent(ComposedMessage::class);
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->sent_at);
    }
}
