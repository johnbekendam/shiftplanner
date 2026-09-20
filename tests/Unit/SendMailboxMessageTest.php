<?php

namespace Tests\Unit;

use App\Jobs\SendMailboxMessage;
use App\Mail\ComposedMessage;
use App\Models\Message;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
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

    public function test_handle_marks_the_listed_assignments_informed(): void
    {
        Mail::fake();
        $listed = ShiftAssignment::factory()->create();
        $other = ShiftAssignment::factory()->create();
        $message = Message::factory()->outbox()->create(['assignment_ids' => [$listed->id]]);

        (new SendMailboxMessage($message->id, $message->recipient_email, new ComposedMessage('Hello', '<p>Body</p>')))->handle();

        $this->assertNotNull($listed->fresh()->informed_at);
        $this->assertEquals($message->fresh()->sent_at, $listed->fresh()->informed_at);
        $this->assertNull($other->fresh()->informed_at);
    }

    public function test_handle_marks_nothing_when_the_send_fails(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));
        $assignment = ShiftAssignment::factory()->create();
        $message = Message::factory()->outbox()->create(['assignment_ids' => [$assignment->id]]);

        try {
            (new SendMailboxMessage($message->id, $message->recipient_email, new ComposedMessage('Hello', '<p>Body</p>')))->handle();
            $this->fail('The job should rethrow the send error.');
        } catch (RuntimeException) {
            // expected: the queue retries the job
        }

        $this->assertNull($assignment->fresh()->informed_at);
        $this->assertSame('outbox', $message->fresh()->status);
    }

    public function test_handle_marks_nothing_for_a_message_without_assignment_ids(): void
    {
        Mail::fake();
        $assignment = ShiftAssignment::factory()->create();
        $message = Message::factory()->outbox()->create(['assignment_ids' => null]);

        (new SendMailboxMessage($message->id, $message->recipient_email, new ComposedMessage('Hello', '<p>Body</p>')))->handle();

        $this->assertNull($assignment->fresh()->informed_at);
    }

    public function test_handle_keeps_an_earlier_informed_time(): void
    {
        Mail::fake();
        $assignment = ShiftAssignment::factory()->create(['informed_at' => '2026-09-01 08:00:00']);
        $message = Message::factory()->outbox()->create(['assignment_ids' => [$assignment->id]]);

        (new SendMailboxMessage($message->id, $message->recipient_email, new ComposedMessage('Hello', '<p>Body</p>')))->handle();

        $this->assertSame('2026-09-01 08:00:00', $assignment->fresh()->informed_at->format('Y-m-d H:i:s'));
    }
}
