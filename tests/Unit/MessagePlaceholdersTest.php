<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\User;
use App\Services\MessagePlaceholders;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagePlaceholdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_name_and_link_for_an_employee(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'Alice']);

        $result = app(MessagePlaceholders::class)->resolve('Hi :name', 'Open :link', $employee);

        $this->assertSame('Hi Alice', $result['subject']);
        $this->assertStringContainsString('/personal/', $result['body']);
        $this->assertSame([], $result['unresolved']);
    }

    public function test_resolves_name_and_link_for_a_user_with_a_linked_employee(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->admin()->create(['name' => 'Bob', 'employee_id' => $employee->id]);

        $result = app(MessagePlaceholders::class)->resolve('Hi :name', 'Open :link', $user);

        $this->assertSame('Hi Bob', $result['subject']);
        $this->assertStringContainsString('/personal/', $result['body']);
        $this->assertSame([], $result['unresolved']);
    }

    public function test_reports_link_unresolved_for_a_user_without_a_linked_employee(): void
    {
        $user = User::factory()->admin()->create(['name' => 'Bob', 'employee_id' => null]);

        $result = app(MessagePlaceholders::class)->resolve('Hi :name', 'Open :link', $user);

        $this->assertSame('Hi Bob', $result['subject']);
        $this->assertStringContainsString(':link', $result['body']);
        $this->assertSame([':link'], $result['unresolved']);
    }

    public function test_leaves_non_registry_tokens_untouched(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'Alice']);

        $result = app(MessagePlaceholders::class)->resolve(':not_a_token', 'Hi :name', $employee);

        $this->assertSame(':not_a_token', $result['subject']);
        $this->assertSame([], $result['unresolved']);
    }

    // ── :planning ───────────────────────────────────────────────────────

    private function plannedEmployee(): Employee
    {
        Carbon::setTestNow('2026-09-20 10:00:00');
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create(['name' => 'Line 1']);
        $early = Shift::factory()->create(['name' => 'Early', 'start_time' => '06:00:00', 'end_time' => '14:00:00']);
        PublishedWeek::query()->create(['week_start' => '2026-09-21', 'workcenter_id' => $workcenter->id]);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id,
            'shift_id' => $early->id, 'date' => '2026-09-23',
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id,
            'shift_id' => $early->id, 'date' => '2026-09-22',
        ]);

        return $employee;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_resolves_planning_to_a_list_of_upcoming_published_shifts(): void
    {
        $employee = $this->plannedEmployee();

        $result = app(MessagePlaceholders::class)->resolve('Plan', "Shifts:\n\n:planning", $employee);

        $this->assertSame([], $result['unresolved']);
        $this->assertSame(
            "Shifts:\n\n- Tuesday 22-09-2026 – Early 06:00–14:00 – Line 1\n- Wednesday 23-09-2026 – Early 06:00–14:00 – Line 1",
            $result['body'],
        );
    }

    public function test_planning_is_unresolved_when_there_are_no_upcoming_published_shifts(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');
        $employee = Employee::factory()->create();

        $result = app(MessagePlaceholders::class)->resolve('Plan', ':planning', $employee);

        $this->assertSame([':planning'], $result['unresolved']);
        $this->assertSame(':planning', $result['body']);
    }

    public function test_resolves_planning_for_a_user_with_a_linked_employee(): void
    {
        $employee = $this->plannedEmployee();
        $user = User::factory()->admin()->create(['employee_id' => $employee->id]);
        $other = User::factory()->admin()->create(['employee_id' => null]);

        $resolved = app(MessagePlaceholders::class)->resolve('Plan', ':planning', $user);
        $unresolved = app(MessagePlaceholders::class)->resolve('Plan', ':planning', $other);

        $this->assertStringContainsString('Early 06:00–14:00', $resolved['body']);
        $this->assertSame([':planning'], $unresolved['unresolved']);
    }

    public function test_planning_is_a_known_token_with_a_sample_for_previews(): void
    {
        $service = app(MessagePlaceholders::class);

        $this->assertContains(':planning', $service->tokens());
        $this->assertNotSame('', $service->sample()[':planning']);
    }
}
