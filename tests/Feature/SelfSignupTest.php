<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Jobs\SendMailboxMessage;
use App\Models\Employee;
use App\Models\Message;
use App\Models\User;
use App\Services\SelfSignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SelfSignupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Step 1: nullable messages.user_id ────────────────────────────────

    public function test_a_message_can_be_stored_without_a_composing_user(): void
    {
        $message = Message::factory()->create(['user_id' => null]);

        $this->assertNull($message->fresh()->user_id);
    }

    public function test_mailbox_list_labels_a_userless_message_as_self_signup(): void
    {
        $this->admin();
        Message::factory()->sent()->create(['user_id' => null]);

        $this->get('/mailbox?tab=sent')->assertInertia(fn ($page) => $page
            ->component('Mailbox')
            ->where('messages.data.0.composed_by', 'Self-signup')
        );
    }

    // ── Step 2: create-or-find plus send ────────────────────────────────

    private function service(): SelfSignupService
    {
        return app(SelfSignupService::class);
    }

    public function test_a_new_email_creates_one_employee_and_queues_one_message(): void
    {
        Queue::fake();

        $this->service()->register('Nina', 'Park', 'nina@example.com');

        $this->assertSame(1, Employee::where('email', 'nina@example.com')->count());
        $employee = Employee::firstWhere('email', 'nina@example.com');
        $this->assertSame('Nina', $employee->first_name);
        $this->assertSame('Park', $employee->last_name);

        $this->assertSame(1, Message::count());
        $message = Message::firstOrFail();
        $this->assertNull($message->user_id);
        $this->assertSame(MessageType::PersonalPageLink, $message->type);
        $this->assertSame('outbox', $message->status);
        $this->assertSame('nina@example.com', $message->recipient_email);
        $this->assertSame('Nina Park', $message->recipient_name);
        Queue::assertPushed(SendMailboxMessage::class, 1);
    }

    public function test_a_known_email_creates_no_employee_and_queues_one_message(): void
    {
        Queue::fake();
        Employee::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lin', 'email' => 'ada@example.com']);

        $this->service()->register('Ada', 'Lin', 'ada@example.com');

        $this->assertSame(1, Employee::count());
        $this->assertSame(1, Message::count());
        Queue::assertPushed(SendMailboxMessage::class, 1);
    }

    public function test_a_known_email_match_is_case_insensitive(): void
    {
        Queue::fake();
        Employee::factory()->create(['email' => 'ada@example.com']);

        $this->service()->register('Ada', 'Lin', 'ADA@example.com');

        $this->assertSame(1, Employee::count());
    }

    public function test_a_differing_name_does_not_change_the_existing_employee(): void
    {
        Queue::fake();
        Employee::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lin', 'email' => 'ada@example.com']);

        $this->service()->register('Adaline', 'Lint', 'ada@example.com');

        $employee = Employee::firstWhere('email', 'ada@example.com');
        $this->assertSame('Ada', $employee->first_name);
        $this->assertSame('Lin', $employee->last_name);
    }

    public function test_the_sent_body_carries_the_employees_personal_link(): void
    {
        Queue::fake();
        $employee = Employee::factory()->create(['email' => 'ada@example.com']);
        $employee->personalLink()->create(['token' => 'known-token']);

        $this->service()->register('Ada', 'Lin', 'ada@example.com');

        $message = Message::firstOrFail();
        $this->assertStringContainsString('/personal/known-token', $message->body);
        $this->assertStringContainsString('/personal/known-token', $message->body_html);
        $this->assertSame(1, $employee->personalLink()->count());
    }

    public function test_the_per_email_limit_skips_a_second_send_in_the_window(): void
    {
        Queue::fake();

        $this->service()->register('Ada', 'Lin', 'ada@example.com');
        $this->service()->register('Ada', 'Lin', 'ada@example.com');

        $this->assertSame(1, Message::count());
        $this->assertSame(1, Employee::count());
        Queue::assertPushed(SendMailboxMessage::class, 1);
    }

    // ── Step 3: POST /signup ────────────────────────────────────────────

    public function test_a_valid_request_registers_and_flashes_the_confirmation(): void
    {
        Queue::fake();

        $this->post('/signup', [
            'first_name' => 'Nina',
            'last_name' => 'Park',
            'email' => 'nina@example.com',
        ])->assertRedirect('/signup')->assertSessionHas('success');

        $this->assertSame(1, Employee::where('email', 'nina@example.com')->count());
        Queue::assertPushed(SendMailboxMessage::class, 1);
    }

    public function test_a_request_missing_fields_is_rejected(): void
    {
        Queue::fake();

        $this->post('/signup', ['first_name' => 'Nina'])
            ->assertSessionHasErrors(['last_name', 'email']);

        $this->assertSame(0, Employee::count());
        Queue::assertNothingPushed();
    }

    public function test_a_second_request_for_the_same_email_still_confirms_but_does_not_resend(): void
    {
        Queue::fake();

        $payload = ['first_name' => 'Ada', 'last_name' => 'Lin', 'email' => 'ada@example.com'];
        $this->post('/signup', $payload)->assertSessionHas('success');
        $this->post('/signup', $payload)->assertSessionHas('success');

        $this->assertSame(1, Message::count());
        Queue::assertPushed(SendMailboxMessage::class, 1);
    }

    public function test_the_route_throttles_a_burst_of_requests(): void
    {
        Queue::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/signup', [
                'first_name' => 'A',
                'last_name' => 'B',
                'email' => "a{$i}@example.com",
            ])->assertRedirect();
        }

        $this->post('/signup', [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'a5@example.com',
        ])->assertStatus(429);
    }

    // ── Step 4: GET /signup ─────────────────────────────────────────────

    public function test_the_signup_page_renders_for_a_guest(): void
    {
        $this->get('/signup')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Auth/AccessCard')
            ->where('activeTab', 'personal-link'));
    }
}
