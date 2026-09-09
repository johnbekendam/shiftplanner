<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Jobs\SendMailboxMessage;
use App\Models\Employee;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailboxTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Access ───────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/mailbox')->assertRedirect('/login');
    }

    public function test_manager_cannot_open_the_mailbox(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/mailbox')->assertForbidden();
    }

    // ── Shared across admins ─────────────────────────────────────────────

    public function test_index_shows_every_admins_messages(): void
    {
        $me = $this->admin();
        $other = User::factory()->admin()->create();

        Message::factory()->for($me)->create(['subject' => 'Mine', 'status' => 'draft']);
        Message::factory()->for($other)->create(['subject' => 'Theirs', 'status' => 'draft']);

        $this->get('/mailbox?tab=draft')->assertInertia(fn ($page) => $page
            ->component('Mailbox')
            ->has('messages.data', 2)
        );
    }

    public function test_an_admin_can_send_another_admins_draft(): void
    {
        Queue::fake();
        $this->admin();
        $other = User::factory()->admin()->create();
        $message = Message::factory()->for($other)->create(['status' => 'draft']);

        $this->post("/mailbox/{$message->id}/send")->assertRedirect();

        $this->assertSame('outbox', $message->fresh()->status);
        Queue::assertPushed(SendMailboxMessage::class);
    }

    public function test_an_admin_can_delete_another_admins_message(): void
    {
        $this->admin();
        $other = User::factory()->admin()->create();
        $message = Message::factory()->for($other)->create();

        $this->delete("/mailbox/{$message->id}")->assertRedirect();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    // ── Compose: personal_page_link ──────────────────────────────────────

    public function test_compose_tab_lists_employees_with_their_full_name(): void
    {
        $this->admin();
        Employee::factory()->create(['first_name' => 'Alice', 'last_name' => 'Ng', 'email' => 'alice@example.com']);

        $this->get('/mailbox?tab=compose')->assertInertia(fn ($page) => $page
            ->component('Mailbox')
            ->where('compose.employees.0.name', 'Alice Ng')
            ->where('compose.employees.0.email', 'alice@example.com')
        );
    }

    public function test_compose_creates_one_draft_per_employee_with_placeholders_resolved(): void
    {
        Queue::fake();
        $this->admin();
        $alice = Employee::factory()->create(['first_name' => 'Alice', 'last_name' => 'Ng', 'email' => 'alice@example.com']);
        $bob = Employee::factory()->create(['first_name' => 'Bob', 'last_name' => 'Li', 'email' => 'bob@example.com']);

        $this->post('/mailbox/compose', [
            'type' => MessageType::PersonalPageLink->value,
            'subject' => 'Your page',
            'body' => "Hi :name,\n\n:link",
            'employee_ids' => [$alice->id, $bob->id],
            'send_mode' => 'draft',
        ])->assertRedirect();

        $this->assertSame(2, Message::count());

        $aliceMessage = Message::where('recipient_email', 'alice@example.com')->firstOrFail();
        $this->assertSame(MessageType::PersonalPageLink, $aliceMessage->type);
        $this->assertSame('Alice Ng', $aliceMessage->recipient_name);
        $this->assertStringContainsString('Hi Alice,', $aliceMessage->body);
        $this->assertStringNotContainsString('Alice Ng', $aliceMessage->body);
        $token = $alice->personalLink->token;
        $this->assertStringContainsString("/personal/{$token}", $aliceMessage->body);
        $this->assertStringContainsString("/personal/{$token}", $aliceMessage->body_html);
        $this->assertSame('draft', $aliceMessage->status);
        Queue::assertNothingPushed();
    }

    public function test_compose_reuses_an_existing_personal_link_token(): void
    {
        $this->admin();
        $employee = Employee::factory()->create();
        $employee->personalLink()->create(['token' => 'existing-token']);

        $this->post('/mailbox/compose', [
            'type' => MessageType::PersonalPageLink->value,
            'subject' => 'S',
            'body' => ':link',
            'employee_ids' => [$employee->id],
            'send_mode' => 'draft',
        ]);

        $this->assertSame(1, $employee->personalLink()->count());
        $this->assertStringContainsString('/personal/existing-token', Message::firstOrFail()->body);
    }

    public function test_compose_queue_mode_sets_outbox_and_dispatches_per_employee(): void
    {
        Queue::fake();
        $this->admin();
        $employees = Employee::factory()->count(2)->create();

        $this->post('/mailbox/compose', [
            'type' => MessageType::PersonalPageLink->value,
            'subject' => 'S',
            'body' => ':link',
            'employee_ids' => $employees->pluck('id')->all(),
            'send_mode' => 'queue',
        ]);

        $this->assertSame(2, Message::where('status', 'outbox')->count());
        Queue::assertPushed(SendMailboxMessage::class, 2);
    }

    public function test_compose_rejects_an_unknown_type(): void
    {
        $this->admin();
        $employee = Employee::factory()->create();

        $this->post('/mailbox/compose', [
            'type' => 'nope',
            'subject' => 'S',
            'body' => 'B',
            'employee_ids' => [$employee->id],
            'send_mode' => 'draft',
        ])->assertSessionHasErrors('type');

        $this->assertSame(0, Message::count());
    }

    public function test_compose_requires_at_least_one_employee(): void
    {
        $this->admin();

        $this->post('/mailbox/compose', [
            'type' => MessageType::PersonalPageLink->value,
            'subject' => 'S',
            'body' => 'B',
            'employee_ids' => [],
            'send_mode' => 'draft',
        ])->assertSessionHasErrors('employee_ids');

        $this->assertSame(0, Message::count());
    }

    // ── Preview ─────────────────────────────────────────────────────────

    public function test_preview_resolves_the_link_for_the_given_employee_without_persisting(): void
    {
        $this->admin();
        $employee = Employee::factory()->create(['first_name' => 'Alice', 'last_name' => 'Ng']);

        $response = $this->postJson('/mailbox/compose/preview', [
            'type' => MessageType::PersonalPageLink->value,
            'subject' => 'Hello :name',
            'body' => 'Open :link',
            'employee_id' => $employee->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('subject', 'Hello Alice');
        $token = $employee->personalLink->token;
        $this->assertStringContainsString("/personal/{$token}", $response->json('html'));
        $this->assertSame(0, Message::count());
    }

    public function test_preview_without_an_employee_uses_sample_values(): void
    {
        $this->admin();

        $response = $this->postJson('/mailbox/compose/preview', [
            'type' => MessageType::PersonalPageLink->value,
            'subject' => 'Hello :name',
            'body' => 'Open :link',
        ]);

        $response->assertOk();
        $response->assertJsonPath('subject', 'Hello '.__('mailbox.preview.sample_name'));
        $this->assertStringContainsString('/personal/EXAMPLE-TOKEN', $response->json('html'));
    }

    // ── Templates ───────────────────────────────────────────────────────

    public function test_admin_updates_a_type_template(): void
    {
        $this->admin();

        $this->put('/mailbox/templates/'.MessageType::PersonalPageLink->value, [
            'subject' => 'New subject',
            'body' => 'New body :link',
        ])->assertRedirect();

        $template = MessageTemplate::forType(MessageType::PersonalPageLink);
        $this->assertSame('New subject', $template->subject);
        $this->assertSame('New body :link', $template->body);
    }

    public function test_template_update_rejects_an_unknown_type(): void
    {
        $this->admin();

        $this->put('/mailbox/templates/not-a-type', [
            'subject' => 'x',
            'body' => 'y',
        ])->assertNotFound();
    }

    public function test_manager_cannot_update_a_template(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put('/mailbox/templates/'.MessageType::PersonalPageLink->value, [
            'subject' => 'x',
            'body' => 'y',
        ])->assertForbidden();
    }

    // ── Send / delete ───────────────────────────────────────────────────

    public function test_send_now_fails_for_a_non_draft_message(): void
    {
        $this->admin();
        $message = Message::factory()->sent()->create();

        $this->post("/mailbox/{$message->id}/send")->assertStatus(422);
    }

    public function test_bulk_delete_removes_only_the_given_ids(): void
    {
        $this->admin();
        $a = Message::factory()->create(['status' => 'draft']);
        $b = Message::factory()->create(['status' => 'draft']);
        $c = Message::factory()->create(['status' => 'draft']);

        $this->post('/mailbox/bulk-delete', ['tab' => 'draft', 'ids' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('messages', ['id' => $a->id]);
        $this->assertDatabaseMissing('messages', ['id' => $b->id]);
        $this->assertDatabaseHas('messages', ['id' => $c->id]);
    }

    public function test_bulk_delete_with_no_ids_clears_the_tab(): void
    {
        $this->admin();
        Message::factory()->count(3)->create(['status' => 'draft']);
        $outbox = Message::factory()->outbox()->create();

        $this->post('/mailbox/bulk-delete', ['tab' => 'draft', 'ids' => []])->assertRedirect();

        $this->assertSame(0, Message::forStatus('draft')->count());
        $this->assertDatabaseHas('messages', ['id' => $outbox->id]);
    }
}
