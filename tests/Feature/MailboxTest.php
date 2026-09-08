<?php

namespace Tests\Feature;

use App\Jobs\SendMailboxMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/mailbox')->assertRedirect('/login');
    }

    public function test_index_only_shows_the_current_users_messages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Message::factory()->for($user)->create(['subject' => 'Mine']);
        Message::factory()->for($other)->create(['subject' => 'Not mine']);

        $response = $this->actingAs($user)->get('/mailbox?tab=draft');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Mailbox')
            ->where('messages.data.0.subject', 'Mine')
            ->has('messages.data', 1)
        );
    }

    public function test_compose_saves_a_draft_without_sending(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mailbox/compose', [
            'to' => 'a@example.com',
            'subject' => 'Hello',
            'body' => 'Just **testing**',
            'send_mode' => 'draft',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('messages', [
            'user_id' => $user->id,
            'recipient_email' => 'a@example.com',
            'subject' => 'Hello',
            'status' => 'draft',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_compose_with_multiple_recipients_creates_one_message_per_recipient(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/mailbox/compose', [
            'to' => 'a@example.com, b@example.com',
            'subject' => 'Hello',
            'body' => 'Body',
            'send_mode' => 'draft',
        ]);

        $this->assertSame(2, Message::forUser($user->id)->count());
        $this->assertDatabaseHas('messages', ['recipient_email' => 'a@example.com']);
        $this->assertDatabaseHas('messages', ['recipient_email' => 'b@example.com']);
    }

    public function test_compose_rejects_invalid_email_address(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mailbox/compose', [
            'to' => 'not-an-email',
            'subject' => 'Hello',
            'body' => 'Body',
            'send_mode' => 'draft',
        ]);

        $response->assertSessionHasErrors('to');
        $this->assertSame(0, Message::count());
    }

    public function test_compose_queue_mode_dispatches_send_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/mailbox/compose', [
            'to' => 'a@example.com',
            'subject' => 'Hello',
            'body' => 'Body',
            'send_mode' => 'queue',
        ]);

        $this->assertDatabaseHas('messages', ['recipient_email' => 'a@example.com', 'status' => 'outbox']);
        Queue::assertPushed(SendMailboxMessage::class);
    }

    public function test_preview_renders_without_persisting(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mailbox/compose/preview', [
            'subject' => 'Hello',
            'body' => 'Some **body**',
        ]);

        $response->assertOk();
        $response->assertJsonPath('subject', 'Hello');
        $this->assertStringContainsString('<strong', $response->json('html'));
        $this->assertStringContainsString('body', $response->json('html'));
        $this->assertSame(0, Message::count());
    }

    public function test_send_now_promotes_a_draft_to_outbox_and_dispatches(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $message = Message::factory()->for($user)->create(['status' => 'draft']);

        $response = $this->actingAs($user)->post("/mailbox/{$message->id}/send");

        $response->assertRedirect();
        $this->assertSame('outbox', $message->fresh()->status);
        Queue::assertPushed(SendMailboxMessage::class);
    }

    public function test_send_now_fails_for_a_non_draft_message(): void
    {
        $user = User::factory()->create();
        $message = Message::factory()->for($user)->sent()->create();

        $this->actingAs($user)->post("/mailbox/{$message->id}/send")->assertStatus(422);
    }

    public function test_cannot_send_someone_elses_draft(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $message = Message::factory()->for($owner)->create(['status' => 'draft']);

        $this->actingAs($intruder)->post("/mailbox/{$message->id}/send")->assertStatus(403);
    }

    public function test_user_can_delete_own_message(): void
    {
        $user = User::factory()->create();
        $message = Message::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/mailbox/{$message->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_cannot_delete_someone_elses_message(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $message = Message::factory()->for($owner)->create();

        $this->actingAs($intruder)->delete("/mailbox/{$message->id}")->assertStatus(403);
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_bulk_delete_removes_only_the_given_ids(): void
    {
        $user = User::factory()->create();
        $a = Message::factory()->for($user)->create(['status' => 'draft']);
        $b = Message::factory()->for($user)->create(['status' => 'draft']);
        $c = Message::factory()->for($user)->create(['status' => 'draft']);

        $response = $this->actingAs($user)->post('/mailbox/bulk-delete', [
            'tab' => 'draft',
            'ids' => [$a->id, $b->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('messages', ['id' => $a->id]);
        $this->assertDatabaseMissing('messages', ['id' => $b->id]);
        $this->assertDatabaseHas('messages', ['id' => $c->id]);
    }

    public function test_bulk_delete_with_no_ids_deletes_everything_in_the_tab(): void
    {
        $user = User::factory()->create();
        Message::factory()->for($user)->count(3)->create(['status' => 'draft']);
        $outboxMessage = Message::factory()->for($user)->outbox()->create();

        $response = $this->actingAs($user)->post('/mailbox/bulk-delete', [
            'tab' => 'draft',
            'ids' => [],
        ]);

        $response->assertRedirect();
        $this->assertSame(0, Message::forUser($user->id)->forStatus('draft')->count());
        $this->assertDatabaseHas('messages', ['id' => $outboxMessage->id]);
    }

    public function test_bulk_delete_with_no_ids_and_a_search_term_only_deletes_matches(): void
    {
        $user = User::factory()->create();
        $match = Message::factory()->for($user)->create(['status' => 'draft', 'subject' => 'Findme']);
        $noMatch = Message::factory()->for($user)->create(['status' => 'draft', 'subject' => 'Other']);

        $response = $this->actingAs($user)->post('/mailbox/bulk-delete', [
            'tab' => 'draft',
            'search' => 'Findme',
            'ids' => [],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('messages', ['id' => $match->id]);
        $this->assertDatabaseHas('messages', ['id' => $noMatch->id]);
    }

    public function test_bulk_delete_only_affects_the_current_users_messages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $theirs = Message::factory()->for($other)->create(['status' => 'draft']);

        $this->actingAs($user)->post('/mailbox/bulk-delete', [
            'tab' => 'draft',
            'ids' => [$theirs->id],
        ]);

        $this->assertDatabaseHas('messages', ['id' => $theirs->id]);
    }
}
