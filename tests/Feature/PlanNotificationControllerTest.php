<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Jobs\SendMailboxMessage;
use App\Models\Employee;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\PublishedWeek;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlanNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    private function plan(Employee $employee, string $date, ?string $informedAt = null): ShiftAssignment
    {
        $workcenter = Workcenter::factory()->create();
        PublishedWeek::query()->create([
            'week_start' => Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString(),
            'workcenter_id' => $workcenter->id,
        ]);

        return ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id,
            'date' => $date, 'informed_at' => $informedAt,
        ]);
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_cannot_send_planning(): void
    {
        $this->post('/planning/send')->assertRedirect('/login');
    }

    public function test_manager_cannot_send_planning(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/planning/send')->assertForbidden();
    }

    // ── Sending ─────────────────────────────────────────────────────────

    public function test_it_queues_one_planning_message_per_uninformed_employee(): void
    {
        Queue::fake();
        $admin = $this->actingAsAdmin();
        $ann = Employee::factory()->create(['first_name' => 'Ann', 'email' => 'ann@example.com']);
        $bo = Employee::factory()->create(['first_name' => 'Bo', 'email' => 'bo@example.com']);
        $done = Employee::factory()->create();
        $annShift = $this->plan($ann, '2026-09-22');
        $this->plan($bo, '2026-09-23');
        $this->plan($done, '2026-09-22', '2026-09-19 08:00:00');

        $this->post('/planning/send')->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, Message::count());
        $message = Message::where('recipient_email', 'ann@example.com')->firstOrFail();
        $this->assertSame(MessageType::Planning, $message->type);
        $this->assertSame('outbox', $message->status);
        $this->assertSame($admin->id, $message->user_id);
        $this->assertSame([$annShift->id], $message->assignment_ids);
        $this->assertStringContainsString('Hi Ann,', $message->body);
        $this->assertStringContainsString('| 22-09-2026 |', $message->body);
        $this->assertSame(0, Message::where('recipient_email', $done->email)->count());
        Queue::assertPushed(SendMailboxMessage::class, 2);
    }

    public function test_it_skips_employees_without_an_email(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $ann = Employee::factory()->create(['email' => 'ann@example.com']);
        $nomail = Employee::factory()->create(['email' => null]);
        $this->plan($ann, '2026-09-22');
        $nomailShift = $this->plan($nomail, '2026-09-22');

        $this->post('/planning/send')->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, Message::count());
        $this->assertSame('ann@example.com', Message::sole()->recipient_email);
        Queue::assertPushed(SendMailboxMessage::class, 1);
        $this->assertNull($nomailShift->fresh()->informed_at);
    }

    public function test_it_refuses_when_only_employees_without_an_email_are_uninformed(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->plan(Employee::factory()->create(['email' => null]), '2026-09-22');

        $this->post('/planning/send')->assertSessionHasErrors('planning');

        $this->assertSame(0, Message::count());
        Queue::assertNothingPushed();
    }

    public function test_it_uses_the_saved_planning_template(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        MessageTemplate::forType(MessageType::Planning)->update([
            'subject' => 'Roster for :name',
            'body' => 'Shifts:\n\n:planning',
        ]);
        $this->plan(Employee::factory()->create(['first_name' => 'Ann']), '2026-09-22');

        $this->post('/planning/send');

        $this->assertSame('Roster for Ann', Message::firstOrFail()->subject);
    }

    public function test_the_flash_message_states_how_many_emails_are_queued(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->plan(Employee::factory()->create(), '2026-09-22');
        $this->plan(Employee::factory()->create(), '2026-09-22');

        $this->post('/planning/send')->assertSessionHas('success', __('planning.flash.sent', ['count' => 2]));
    }

    public function test_sending_marks_the_shifts_informed_so_nothing_is_left_to_send(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $assignment = $this->plan(Employee::factory()->create(), '2026-09-22');

        $this->post('/planning/send')->assertRedirect();

        $this->assertNotNull($assignment->fresh()->informed_at);
        $this->get('/planning')->assertInertia(fn ($page) => $page->where('uninformedCount', 0));
        $this->post('/planning/send')->assertSessionHasErrors('planning');
        $this->assertSame(1, Message::count());
    }

    public function test_a_second_send_before_the_emails_are_delivered_queues_nothing_more(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->plan(Employee::factory()->create(), '2026-09-22');

        $this->post('/planning/send')->assertSessionHas('success');
        $this->post('/planning/send')->assertSessionHasErrors('planning');

        $this->assertSame(1, Message::count());
        Queue::assertPushed(SendMailboxMessage::class, 1);
        $this->get('/planning')->assertInertia(fn ($page) => $page->where('uninformedCount', 0));
    }

    public function test_it_refuses_when_nobody_is_uninformed(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->plan(Employee::factory()->create(), '2026-09-22', '2026-09-19 08:00:00');

        $this->post('/planning/send')->assertSessionHasErrors('planning');

        $this->assertSame(0, Message::count());
        Queue::assertNothingPushed();
    }

    public function test_it_refuses_when_the_template_has_no_planning_placeholder(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        MessageTemplate::forType(MessageType::Planning)->update(['subject' => 'Hi', 'body' => 'Hello :name']);
        $this->plan(Employee::factory()->create(), '2026-09-22');

        $this->post('/planning/send')->assertSessionHasErrors('planning');

        $this->assertSame(0, Message::count());
        Queue::assertNothingPushed();
    }

    // ── Planning page prop ──────────────────────────────────────────────

    public function test_the_planning_page_shares_the_number_of_uninformed_employees(): void
    {
        $this->actingAsAdmin();
        $ann = Employee::factory()->create();
        $this->plan($ann, '2026-09-22');
        $this->plan($ann, '2026-09-23');
        $this->plan(Employee::factory()->create(), '2026-09-22');
        $this->plan(Employee::factory()->create(), '2026-09-22', '2026-09-19 08:00:00');

        $this->get('/planning')->assertInertia(fn ($page) => $page->where('uninformedCount', 2));
    }

    public function test_the_planning_page_count_leaves_out_employees_without_an_email(): void
    {
        $this->actingAsAdmin();
        $this->plan(Employee::factory()->create(['email' => 'a@example.com']), '2026-09-22');
        $this->plan(Employee::factory()->create(['email' => null]), '2026-09-22');

        $this->get('/planning')->assertInertia(fn ($page) => $page->where('uninformedCount', 1));
    }
}
