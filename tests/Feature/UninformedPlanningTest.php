<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\PublishedWeek;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Services\UninformedPlanning;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UninformedPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-20 10:00:00'); // a Sunday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Assigns the employee on a date. Publishes that week for the workcenter unless told not to. */
    private function assign(Employee $employee, string $date, ?Workcenter $workcenter = null, bool $published = true, ?string $informedAt = null): ShiftAssignment
    {
        $workcenter ??= Workcenter::factory()->create();

        if ($published) {
            PublishedWeek::query()->firstOrCreate([
                'week_start' => Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString(),
                'workcenter_id' => $workcenter->id,
            ]);
        }

        return ShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'date' => $date,
            'informed_at' => $informedAt,
        ]);
    }

    private function service(): UninformedPlanning
    {
        return app(UninformedPlanning::class);
    }

    // ── Schema and type ─────────────────────────────────────────────────

    public function test_an_assignment_casts_informed_at_to_a_date(): void
    {
        $assignment = $this->assign(Employee::factory()->create(), '2026-09-22', informedAt: '2026-09-19 08:00:00');

        $this->assertInstanceOf(Carbon::class, $assignment->fresh()->informed_at);
    }

    public function test_a_message_casts_assignment_ids_to_an_array(): void
    {
        $message = Message::factory()->create(['type' => MessageType::Planning, 'assignment_ids' => [3, 5]]);

        $this->assertSame([3, 5], $message->fresh()->assignment_ids);
        $this->assertSame(MessageType::Planning, $message->fresh()->type);
    }

    public function test_planning_is_a_composable_type_with_a_seeded_template(): void
    {
        $this->assertTrue(MessageType::Planning->composable());

        $template = MessageTemplate::forType(MessageType::Planning);

        $this->assertNotSame('', $template->subject);
        $this->assertStringContainsString(':name', $template->body);
        $this->assertStringContainsString(':planning', $template->body);
        $this->assertStringContainsString(':button:link', $template->body);
    }

    // ── upcomingFor ─────────────────────────────────────────────────────

    public function test_upcoming_for_lists_published_assignments_from_today_in_date_order(): void
    {
        $employee = Employee::factory()->create();
        $later = $this->assign($employee, '2026-09-29');
        $today = $this->assign($employee, '2026-09-20');
        $informed = $this->assign($employee, '2026-09-22', informedAt: '2026-09-19 08:00:00');

        $ids = $this->service()->upcomingFor($employee)->pluck('id')->all();

        $this->assertSame([$today->id, $informed->id, $later->id], $ids);
    }

    public function test_upcoming_for_skips_past_unpublished_and_other_employees(): void
    {
        $employee = Employee::factory()->create();
        $this->assign($employee, '2026-09-19'); // past
        $this->assign($employee, '2026-09-22', published: false);
        $this->assign(Employee::factory()->create(), '2026-09-22');
        $kept = $this->assign($employee, '2026-09-23');

        $this->assertSame([$kept->id], $this->service()->upcomingFor($employee)->pluck('id')->all());
    }

    public function test_a_week_published_for_another_workcenter_does_not_count(): void
    {
        $employee = Employee::factory()->create();
        $published = Workcenter::factory()->create();
        $other = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-21', 'workcenter_id' => $published->id]);
        $this->assign($employee, '2026-09-22', $other, published: false);

        $this->assertTrue($this->service()->upcomingFor($employee)->isEmpty());
    }

    // ── summary ─────────────────────────────────────────────────────────

    public function test_summary_counts_only_uninformed_upcoming_published_shifts(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'Ann', 'last_name' => 'Bee']);
        $this->assign($employee, '2026-09-24');
        $this->assign($employee, '2026-09-22');
        $this->assign($employee, '2026-09-23', informedAt: '2026-09-19 08:00:00');
        $this->assign($employee, '2026-09-25', published: false);
        $this->assign($employee, '2026-09-18'); // past

        $rows = $this->service()->summary();

        $this->assertCount(1, $rows);
        $this->assertSame($employee->id, $rows[0]['id']);
        $this->assertSame('Ann Bee', $rows[0]['name']);
        $this->assertSame(2, $rows[0]['uninformed_count']);
        $this->assertSame('2026-09-22', $rows[0]['first_date']);
    }

    public function test_summary_leaves_out_fully_informed_employees(): void
    {
        $this->assign(Employee::factory()->create(), '2026-09-22', informedAt: '2026-09-19 08:00:00');

        $this->assertSame([], $this->service()->summary()->all());
    }

    public function test_summary_is_ordered_by_name_and_shows_the_business_line(): void
    {
        $line = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        $zed = Employee::factory()->create(['first_name' => 'Zed', 'last_name' => 'A', 'business_line_id' => $line->id]);
        $amy = Employee::factory()->create(['first_name' => 'Amy', 'last_name' => 'B', 'business_line_id' => null]);
        $this->assign($zed, '2026-09-22');
        $this->assign($amy, '2026-09-22');

        $rows = $this->service()->summary();

        $this->assertSame(['Amy B', 'Zed A'], $rows->pluck('name')->all());
        $this->assertNull($rows[0]['business_line']);
        $this->assertSame('PMP', $rows[1]['business_line']);
    }

    public function test_summary_can_filter_by_business_line(): void
    {
        $line = BusinessLine::factory()->create();
        $inLine = Employee::factory()->create(['business_line_id' => $line->id]);
        $this->assign($inLine, '2026-09-22');
        $this->assign(Employee::factory()->create(), '2026-09-22');

        $rows = $this->service()->summary($line->id);

        $this->assertSame([$inLine->id], $rows->pluck('id')->all());
    }
}
