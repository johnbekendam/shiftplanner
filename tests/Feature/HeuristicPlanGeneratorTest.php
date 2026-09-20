<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\PlanGenerationRun;
use App\Models\PlanningRule;
use App\Models\PublishedWeek;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftDateOverride;
use App\Services\Planning\HeuristicPlanGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeuristicPlanGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private const CYCLE_START = '2026-09-07'; // a Monday

    private function generator(): HeuristicPlanGenerator
    {
        return app(HeuristicPlanGenerator::class);
    }

    private function makeRun(string $cycleStart = self::CYCLE_START): PlanGenerationRun
    {
        return PlanGenerationRun::create(['cycle_start' => $cycleStart, 'status' => PlanGenerationRun::STATUS_PENDING]);
    }

    private function workcenterWithOverride(string $date, int $spots = 1): array
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach($shift);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date, 'spots' => $spots,
        ]);

        return [$workcenter, $shift];
    }

    /** A confirmed employee explicitly marked available for $shift on $date's weekday — the app treats a missing row as unavailable, not available. */
    private function eligibleEmployee(Shift $shift, string $date): Employee
    {
        $employee = Employee::factory()->create(['confirmed' => true, 'weekly_hours' => 40]);
        $this->makeAvailable($employee, $shift, $date);

        return $employee;
    }

    private function makeAvailable(Employee $employee, Shift $shift, string $date): void
    {
        RecurringAvailability::query()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'weekday' => Carbon::parse($date)->isoWeekday(),
            'level' => 'available',
        ]);
    }

    public function test_fills_an_open_spot_with_the_only_eligible_employee(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = $this->eligibleEmployee($shift, '2026-09-08');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', [
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08',
        ]);
        $run->refresh();
        $this->assertSame(PlanGenerationRun::STATUS_DONE, $run->status);
        $this->assertSame([], $run->unfulfilled);
        $this->assertCount(1, $run->changes);
        $this->assertSame('added', $run->changes[0]['type']);
    }

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

    public function test_a_hidden_shift_gets_no_assignments(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['visible_by_default' => false]);
        $workcenter->shifts()->attach($shift);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08', 'spots' => 1,
        ]);
        $this->eligibleEmployee($shift, '2026-09-08');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        // Hidden shifts never become an open cell at all — not even an unfulfilled one.
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_most_constrained_cell_is_filled_before_an_easier_one_starves_it(): void
    {
        // Two cells same date: A has 2 eligible candidates, B has only 1 (the same
        // one A could also use). Filling A first with its "only" option would strand
        // B; most-constrained-first must fill B before A takes B's only candidate.
        [$workcenterA, $shiftA] = $this->workcenterWithOverride('2026-09-08');
        $workcenterB = Workcenter::factory()->create();
        $shiftB = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $workcenterB->shifts()->attach($shiftB);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenterB->id, 'shift_id' => $shiftB->id, 'date' => '2026-09-08', 'spots' => 1,
        ]);

        $shared = $this->eligibleEmployee($shiftA, '2026-09-08');
        $this->makeAvailable($shared, $shiftB, '2026-09-08');
        $onlyForA = $this->eligibleEmployee($shiftA, '2026-09-08');
        // $shared is the only candidate eligible for B (hard-restricted to workcenter B);
        // A has both $shared and $onlyForA available.
        $shared->workcenters()->attach($workcenterB->id, ['mode' => 'hard']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', [
            'employee_id' => $shared->id, 'workcenter_id' => $workcenterB->id, 'shift_id' => $shiftB->id, 'date' => '2026-09-08',
        ]);
        $this->assertDatabaseHas('shift_assignments', [
            'employee_id' => $onlyForA->id, 'workcenter_id' => $workcenterA->id, 'shift_id' => $shiftA->id, 'date' => '2026-09-08',
        ]);
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_unfulfilled_reason_is_hard_cap_reached_once_the_only_candidate_is_capped_elsewhere(): void
    {
        [, $shiftA] = $this->workcenterWithOverride('2026-09-08');
        $workcenterB = Workcenter::factory()->create();
        $shiftB = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $workcenterB->shifts()->attach($shiftB);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenterB->id, 'shift_id' => $shiftB->id, 'date' => '2026-09-08', 'spots' => 1,
        ]);
        // The only employee at all — eligible for both cells.
        $employee = $this->eligibleEmployee($shiftA, '2026-09-08');
        $this->makeAvailable($employee, $shiftB, '2026-09-08');
        PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'hard', 'config' => ['value' => 1]]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertCount(1, ShiftAssignment::all());
        $unfulfilled = $run->refresh()->unfulfilled;
        $this->assertCount(1, $unfulfilled);
        $this->assertSame('hard_cap_reached', $unfulfilled[0]['reason']);
    }

    public function test_a_fixed_assignment_is_never_touched_and_its_spot_stays_filled(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08', spots: 1);
        $fixedEmployee = $this->eligibleEmployee($shift, '2026-09-08');
        $assignment = ShiftAssignment::factory()->create([
            'employee_id' => $fixedEmployee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
            'date' => '2026-09-08', 'fixed' => true,
        ]);
        $this->eligibleEmployee($shift, '2026-09-08'); // would otherwise be eligible for this cell

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['id' => $assignment->id, 'employee_id' => $fixedEmployee->id]);
        $this->assertSame(1, ShiftAssignment::count()); // the spot was already full — nothing added for the other employee
        $this->assertSame([], $run->refresh()->changes);
    }

    public function test_a_published_assignment_is_never_touched(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08', spots: 1);
        $publishedEmployee = $this->eligibleEmployee($shift, '2026-09-08');
        $assignment = ShiftAssignment::factory()->create([
            'employee_id' => $publishedEmployee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
            'date' => '2026-09-08', 'fixed' => false,
        ]);
        PublishedWeek::query()->create(['week_start' => self::CYCLE_START, 'workcenter_id' => $workcenter->id]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['id' => $assignment->id, 'employee_id' => $publishedEmployee->id]);
        $this->assertSame(1, ShiftAssignment::count());
        $this->assertSame([], $run->refresh()->changes);
    }

    // ── Published weeks: frozen unless planner_open ─────────────────────

    public function test_the_open_spot_of_a_published_week_is_not_filled(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08', spots: 1);
        $this->eligibleEmployee($shift, '2026-09-08');
        PublishedWeek::query()->create(['week_start' => self::CYCLE_START, 'workcenter_id' => $workcenter->id]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $run->refresh();
        $this->assertSame(PlanGenerationRun::STATUS_DONE, $run->status);
        $this->assertSame([], $run->changes);
    }

    public function test_a_frozen_open_spot_is_not_reported_as_unfulfilled(): void
    {
        [$workcenter] = $this->workcenterWithOverride('2026-09-08', spots: 1); // nobody is eligible
        PublishedWeek::query()->create(['week_start' => self::CYCLE_START, 'workcenter_id' => $workcenter->id]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_the_open_spot_of_a_published_week_is_filled_when_the_planner_is_allowed(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08', spots: 2);
        $publishedEmployee = $this->eligibleEmployee($shift, '2026-09-08');
        $existing = ShiftAssignment::factory()->create([
            'employee_id' => $publishedEmployee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
            'date' => '2026-09-08', 'fixed' => false,
        ]);
        $newEmployee = $this->eligibleEmployee($shift, '2026-09-08');
        PublishedWeek::query()->create([
            'week_start' => self::CYCLE_START, 'workcenter_id' => $workcenter->id, 'planner_open' => true,
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['id' => $existing->id, 'employee_id' => $publishedEmployee->id]);
        $this->assertDatabaseHas('shift_assignments', [
            'employee_id' => $newEmployee->id, 'workcenter_id' => $workcenter->id, 'date' => '2026-09-08',
        ]);
        $this->assertSame(2, ShiftAssignment::count());
        $changes = $run->refresh()->changes;
        $this->assertCount(1, $changes);
        $this->assertSame('added', $changes[0]['type']);
    }

    public function test_an_allowed_planner_never_moves_the_existing_assignments_of_a_published_week(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08', spots: 2);
        $kept = [];
        foreach (range(1, 2) as $i) {
            $employee = $this->eligibleEmployee($shift, '2026-09-08');
            $kept[] = ShiftAssignment::factory()->create([
                'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
                'date' => '2026-09-08', 'fixed' => false,
            ]);
        }
        foreach (range(1, 3) as $i) {
            $this->eligibleEmployee($shift, '2026-09-08');
        }
        PublishedWeek::query()->create([
            'week_start' => self::CYCLE_START, 'workcenter_id' => $workcenter->id, 'planner_open' => true,
        ]);

        $this->generator()->generate($this->makeRun());

        foreach ($kept as $assignment) {
            $this->assertDatabaseHas('shift_assignments', ['id' => $assignment->id, 'employee_id' => $assignment->employee_id]);
        }
        $this->assertSame(2, ShiftAssignment::count());
    }

    public function test_a_frozen_week_does_not_block_other_weeks_or_workcenters(): void
    {
        [$frozenWorkcenter, $shift] = $this->workcenterWithOverride('2026-09-08', spots: 1);
        $otherWorkcenter = Workcenter::factory()->create();
        $otherWorkcenter->shifts()->attach($shift);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $otherWorkcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08', 'spots' => 1,
        ]);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $frozenWorkcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-15', 'spots' => 1,
        ]);
        $this->eligibleEmployee($shift, '2026-09-08');
        $this->eligibleEmployee($shift, '2026-09-15');
        $this->eligibleEmployee($shift, '2026-09-08');
        PublishedWeek::query()->create(['week_start' => self::CYCLE_START, 'workcenter_id' => $frozenWorkcenter->id]);

        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseMissing('shift_assignments', ['workcenter_id' => $frozenWorkcenter->id, 'date' => '2026-09-08']);
        $this->assertDatabaseHas('shift_assignments', ['workcenter_id' => $otherWorkcenter->id, 'date' => '2026-09-08']);
        $this->assertDatabaseHas('shift_assignments', ['workcenter_id' => $frozenWorkcenter->id, 'date' => '2026-09-15']);
    }

    public function test_an_open_spot_in_an_allowed_week_with_no_candidate_is_reported_unfulfilled(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08', spots: 1);
        PublishedWeek::query()->create([
            'week_start' => self::CYCLE_START, 'workcenter_id' => $workcenter->id, 'planner_open' => true,
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame('no_eligible_employee', $run->refresh()->unfulfilled[0]['reason']);
    }

    public function test_a_pre_existing_non_locked_assignment_is_left_alone_by_construction(): void
    {
        // Construction only fills currently-open cells; it never relocates anyone
        // (that's the hill-climbing phase). A manual, non-fixed assignment already
        // filling a cell should be reported as unchanged, not re-created or removed.
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08', spots: 1);
        $employee = $this->eligibleEmployee($shift, '2026-09-08');
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
            'date' => '2026-09-08', 'fixed' => false,
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(1, ShiftAssignment::count());
        $this->assertSame([], $run->refresh()->changes);
    }

    public function test_outside_the_cycle_dates_are_ignored(): void
    {
        // One day after the 14-day cycle ends.
        [, $shift] = $this->workcenterWithOverride('2026-09-21');
        $this->eligibleEmployee($shift, '2026-09-21');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame([], $run->refresh()->unfulfilled);
    }
}
