<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\PlanningRule;
use App\Models\PlanningSettings;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EligibleEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    /** A Tuesday, so recurring_availabilities (weekday-only, Mon-Fri) can constrain it. */
    private function aTuesday(): string
    {
        return '2026-09-15';
    }

    private function url(Workcenter $workcenter, Shift $shift): string
    {
        WorkcenterShiftCapacity::query()->firstOrCreate([
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'weekday' => 2,
        ], ['spots' => 10]);

        return "/planning/eligible-employees?workcenter_id={$workcenter->id}&shift_id={$shift->id}&date={$this->aTuesday()}";
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_is_redirected(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->get($this->url($workcenter, $shift))->assertRedirect('/login');
    }

    public function test_manager_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create());
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->get($this->url($workcenter, $shift))->assertForbidden();
    }

    // ── Eligibility ────────────────────────────────────────────────────

    public function test_archived_employees_are_excluded(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $active = Employee::factory()->create(['confirmed' => true]);
        Employee::factory()->create(['confirmed' => true, 'archived_at' => now()]);

        $ids = collect($this->get($this->url($workcenter, $shift))->json())->pluck('id');

        $this->assertSame([$active->id], $ids->all());
    }

    public function test_marks_an_employee_already_assigned_to_this_cell_as_blocked(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => true]);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id, 'date' => $this->aTuesday(),
        ]);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('duplicate', $entry['block_reason']);
    }

    public function test_marks_an_employee_on_holiday_as_blocked(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => true]);
        EmployeeHoliday::factory()->create([
            'employee_id' => $employee->id, 'start_date' => $this->aTuesday(), 'end_date' => $this->aTuesday(),
        ]);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('holiday', $entry['block_reason']);
    }

    public function test_marks_an_unavailable_employee_as_blocked(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => true]);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $shift->id, 'level' => 'unavailable',
        ]);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('unavailable', $entry['block_reason']);
    }

    public function test_marks_an_employee_with_availability_not_set_as_blocked(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => true]);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('unavailable', $entry['block_reason']);
    }

    public function test_marks_an_employee_as_blocked_when_the_shift_is_hidden(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['visible_by_default' => false]);
        $employee = Employee::factory()->create(['confirmed' => true]);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('shift_hidden', $entry['block_reason']);
    }

    public function test_includes_a_hard_workcenter_employee_for_a_hidden_workcenter_shift(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['visible_by_default' => false]);
        $workcenter->shifts()->attach($shift);
        $employee = Employee::factory()->create(['confirmed' => true]);
        $employee->workcenters()->attach($workcenter, ['mode' => 'hard']);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $shift->id, 'level' => 'available',
        ]);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertNull($entry['block_reason']);
    }

    public function test_marks_an_employee_with_a_same_date_overlapping_assignment_as_blocked(): void
    {
        $this->actingAsAdmin();
        $employee = Employee::factory()->create(['confirmed' => true]);
        $otherWorkcenter = Workcenter::factory()->create();
        $overlappingShift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $otherWorkcenter->id,
            'shift_id' => $overlappingShift->id, 'date' => $this->aTuesday(),
        ]);

        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '08:00', 'end_time' => '16:00']);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $shift->id, 'level' => 'available',
        ]);

        $employee->workcenters()->attach($workcenter, ['mode' => 'hard']);
        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('overlap', $entry['block_reason']);
    }

    public function test_includes_a_not_preferred_employee_flagged(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => true]);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $shift->id, 'level' => 'not_preferred',
        ]);

        $employee->workcenters()->attach($workcenter, ['mode' => 'hard']);
        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertNotNull($entry);
        $this->assertNull($entry['block_reason']);
        $this->assertTrue($entry['not_preferred']);
    }

    public function test_includes_an_otherwise_unconstrained_employee(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => true]);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $shift->id, 'level' => 'available',
        ]);

        $employee->workcenters()->attach($workcenter, ['mode' => 'hard']);
        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertNotNull($entry);
        $this->assertNull($entry['block_reason']);
        $this->assertFalse($entry['not_preferred']);
    }

    public function test_marks_an_employee_hard_assigned_to_a_different_workcenter_as_blocked(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $otherWorkcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => true]);
        $employee->workcenters()->attach($otherWorkcenter, ['mode' => 'hard']);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $shift->id, 'level' => 'available',
        ]);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('workcenter_ineligible', $entry['block_reason']);
    }

    public function test_includes_an_employee_hard_assigned_to_this_workcenter(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => true]);
        $employee->workcenters()->attach($workcenter, ['mode' => 'hard']);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $shift->id, 'level' => 'available',
        ]);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertNull($entry['block_reason']);
    }

    public function test_marks_an_employee_soft_assigned_to_a_different_workcenter_as_blocked(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $otherWorkcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => true]);
        $employee->workcenters()->attach($otherWorkcenter, ['mode' => 'soft']);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $shift->id, 'level' => 'available',
        ]);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('workcenter_ineligible', $entry['block_reason']);
    }

    public function test_marks_an_employee_without_workcenters_as_blocked(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => true]);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $shift->id, 'level' => 'available',
        ]);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('workcenter_ineligible', $entry['block_reason']);
    }

    public function test_does_not_send_a_workcenter_not_preferred_flag(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => true]);
        $employee->workcenters()->attach($workcenter, ['mode' => 'soft']);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertArrayNotHasKey('workcenter_not_preferred', $entry);
    }

    public function test_excludes_an_unconfirmed_employee(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => false]);

        $ids = collect($this->get($this->url($workcenter, $shift))->json())->pluck('id');

        $this->assertNotContains($employee->id, $ids);
    }

    public function test_marks_an_employee_at_the_hard_daily_shift_cap_as_blocked(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $otherWorkcenter = Workcenter::factory()->create();
        $existingShift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $targetShift = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $employee = Employee::factory()->create(['confirmed' => true]);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $targetShift->id, 'level' => 'available',
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $otherWorkcenter->id,
            'shift_id' => $existingShift->id, 'date' => $this->aTuesday(),
        ]);
        PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'hard', 'config' => ['value' => 1]]);

        $employee->workcenters()->attach($workcenter, ['mode' => 'hard']);
        $entry = collect($this->get($this->url($workcenter, $targetShift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('max_shifts_per_day', $entry['block_reason']);
    }

    public function test_marks_an_employee_over_the_hard_hours_cap_for_the_planning_cycle_as_blocked(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-14']);
        $workcenter = Workcenter::factory()->create();
        $existingShift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $targetShift = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $employee = Employee::factory()->create(['confirmed' => true, 'weekly_hours' => 4]);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $targetShift->id, 'level' => 'available',
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => Workcenter::factory()->create()->id,
            'shift_id' => $existingShift->id, 'date' => '2026-09-14',
        ]);
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $employee->workcenters()->attach($workcenter, ['mode' => 'hard']);
        $entry = collect($this->get($this->url($workcenter, $targetShift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('max_hours_per_week', $entry['block_reason']);
    }

    public function test_marks_an_employee_over_the_hard_hours_cap_for_one_week_as_blocked(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-14']);
        $workcenter = Workcenter::factory()->create();
        $existingShift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $targetShift = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $employee = Employee::factory()->create(['confirmed' => true, 'weekly_hours' => 8]);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $targetShift->id, 'level' => 'available',
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => Workcenter::factory()->create()->id,
            'shift_id' => $existingShift->id, 'date' => '2026-09-14',
        ]);
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $employee->workcenters()->attach($workcenter, ['mode' => 'hard']);
        $entry = collect($this->get($this->url($workcenter, $targetShift))->json())->firstWhere('id', $employee->id);

        $this->assertSame('max_hours_per_week_distribution', $entry['block_reason']);
    }

    public function test_counts_slightly_long_shifts_as_four_hour_blocks_for_the_hard_hours_cap(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-14']);
        $workcenter = Workcenter::factory()->create();
        $existingShift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:15']); // 8.25h counts as 8
        $targetShift = Shift::factory()->create(['start_time' => '14:15', 'end_time' => '23:00']); // 8.75h counts as 8
        $employee = Employee::factory()->create(['confirmed' => true, 'weekly_hours' => 12]); // weekly cap 16h
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $targetShift->id, 'level' => 'available',
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => Workcenter::factory()->create()->id,
            'shift_id' => $existingShift->id, 'date' => '2026-09-14',
        ]);
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $employee->workcenters()->attach($workcenter, ['mode' => 'hard']);
        $entry = collect($this->get($this->url($workcenter, $targetShift))->json())->firstWhere('id', $employee->id);

        $this->assertNull($entry['block_reason']);
    }
}
