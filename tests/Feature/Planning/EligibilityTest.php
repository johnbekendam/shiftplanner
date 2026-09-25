<?php

namespace Tests\Feature\Planning;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EligibilityTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    public function test_ignores_an_employee_who_is_not_confirmed(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = Employee::factory()->create(['confirmed' => false, 'weekly_hours' => 40]);
        $this->makeAvailable($employee, $shift, '2026-09-08');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame('no_eligible_employee', $run->refresh()->unfulfilled[0]['reason']);
    }

    public function test_ignores_an_employee_with_zero_weekly_hours(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = Employee::factory()->create(['confirmed' => true, 'weekly_hours' => 0]);
        $this->makeAvailable($employee, $shift, '2026-09-08');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
    }

    public function test_a_holiday_makes_the_only_candidate_unavailable(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = $this->eligibleEmployee($shift, '2026-09-08');
        EmployeeHoliday::query()->create([
            'employee_id' => $employee->id, 'start_date' => '2026-09-08', 'end_date' => '2026-09-08',
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $unfulfilled = $run->refresh()->unfulfilled;
        $this->assertCount(1, $unfulfilled);
        $this->assertSame('no_eligible_employee', $unfulfilled[0]['reason']);
    }

    // Each test below runs the planner twice on the same data: first with the
    // blocking condition (nobody is assigned), then with the condition removed
    // (the same employee is assigned). The second run is the control.

    public function test_a_holiday_blocks_only_its_own_dates_inclusive_of_both_ends(): void
    {
        // The cell is on Tuesday 2026-09-08. A holiday from 09-08 to 09-10 blocks it
        // on its first day, one from 09-06 to 09-08 blocks it on its last day, and
        // neither one ending the day before nor one starting the day after does.
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = $this->eligibleEmployee($shift, '2026-09-08');

        foreach ([['2026-09-08', '2026-09-10'], ['2026-09-06', '2026-09-08']] as [$start, $end]) {
            $holiday = EmployeeHoliday::query()->create(['employee_id' => $employee->id, 'start_date' => $start, 'end_date' => $end]);
            $this->generator()->generate($this->makeRun());
            $this->assertSame(0, ShiftAssignment::count(), "holiday {$start} to {$end} must block 2026-09-08");
            $holiday->delete();
        }

        foreach ([['2026-09-05', '2026-09-07'], ['2026-09-09', '2026-09-12']] as [$start, $end]) {
            $holiday = EmployeeHoliday::query()->create(['employee_id' => $employee->id, 'start_date' => $start, 'end_date' => $end]);
            $this->generator()->generate($this->makeRun());
            $this->assertSame(1, ShiftAssignment::count(), "holiday {$start} to {$end} must not block 2026-09-08");
            ShiftAssignment::query()->delete();
            $holiday->delete();
        }
    }

    public function test_an_unavailable_cell_is_never_assigned(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = $this->employee();
        $this->makeAvailable($employee, $shift, '2026-09-08', 'unavailable');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame('no_eligible_employee', $run->refresh()->unfulfilled[0]['reason']);

        RecurringAvailability::query()->where('employee_id', $employee->id)->update(['level' => 'available']);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $employee->id, 'date' => '2026-09-08']);
    }

    public function test_a_missing_availability_row_counts_as_unavailable(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = $this->employee(); // no availability rows at all

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame('no_eligible_employee', $run->refresh()->unfulfilled[0]['reason']);

        $this->makeAvailable($employee, $shift, '2026-09-08');
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $employee->id, 'date' => '2026-09-08']);
    }

    public function test_availability_applies_to_its_own_weekday_and_shift_only(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08'); // Tuesday
        $otherShift = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $employee = $this->employee();
        $this->makeAvailable($employee, $shift, '2026-09-09'); // right shift, Wednesday
        $this->makeAvailable($employee, $otherShift, '2026-09-08'); // Tuesday, wrong shift

        $this->generator()->generate($this->makeRun());

        $this->assertSame(0, ShiftAssignment::count());
    }

    public function test_a_not_preferred_cell_is_still_assigned_when_it_is_the_only_option(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = $this->employee();
        $this->makeAvailable($employee, $shift, '2026-09-08', 'not_preferred');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $employee->id, 'date' => '2026-09-08']);
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_a_workcenter_membership_restricts_the_employee_to_those_workcenters(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08');
        $otherWorkcenter = Workcenter::factory()->create();
        $employee = $this->eligibleEmployee($shift, '2026-09-08');
        $employee->workcenters()->attach($otherWorkcenter->id);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame('no_eligible_employee', $run->refresh()->unfulfilled[0]['reason']);

        $employee->workcenters()->attach($workcenter->id);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $employee->id, 'workcenter_id' => $workcenter->id]);
    }

    public function test_an_employee_without_workcenters_is_not_planned(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = Employee::factory()->create(['confirmed' => true, 'weekly_hours' => 40]);
        $this->makeAvailable($employee, $shift, '2026-09-08');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame('no_eligible_employee', $run->refresh()->unfulfilled[0]['reason']);
    }

    public function test_an_employee_is_not_planned_on_two_overlapping_shifts(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08'); // 06:00-14:00
        $overlapping = Shift::factory()->create(['start_time' => '10:00', 'end_time' => '18:00']);
        $otherWorkcenter = Workcenter::factory()->create();
        $this->openCell($otherWorkcenter, $overlapping, '2026-09-08');
        $employee = $this->eligibleEmployee($shift, '2026-09-08');
        $this->makeAvailable($employee, $overlapping, '2026-09-08');
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
            'date' => '2026-09-08', 'fixed' => true,
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(1, ShiftAssignment::count());
        $this->assertCount(1, $run->refresh()->unfulfilled);
    }

    public function test_an_employee_is_planned_on_two_shifts_that_only_touch(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08'); // 06:00-14:00
        $adjacent = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $otherWorkcenter = Workcenter::factory()->create();
        $this->openCell($otherWorkcenter, $adjacent, '2026-09-08');
        $employee = $this->eligibleEmployee($shift, '2026-09-08');
        $this->makeAvailable($employee, $adjacent, '2026-09-08');
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
            'date' => '2026-09-08', 'fixed' => true,
        ]);

        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $employee->id, 'shift_id' => $adjacent->id]);
    }
}
