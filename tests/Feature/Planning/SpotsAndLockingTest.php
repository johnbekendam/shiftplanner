<?php

namespace Tests\Feature\Planning;

use App\Models\Employee;
use App\Models\PlanGenerationRun;
use App\Models\PlanningRule;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpotsAndLockingTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

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

    public function test_a_successful_run_freezes_the_open_weeks_of_its_cycle_again(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08', spots: 1);
        $this->eligibleEmployee($shift, '2026-09-08');
        $firstWeek = PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $workcenter->id, 'planner_open' => true]);
        $secondWeek = PublishedWeek::query()->create(['week_start' => '2026-09-14', 'workcenter_id' => $workcenter->id, 'planner_open' => true]);

        $this->generator()->generate($this->makeRun());

        $this->assertFalse($firstWeek->fresh()->planner_open);
        $this->assertFalse($secondWeek->fresh()->planner_open);
    }

    public function test_the_open_flag_is_cleared_even_when_no_spot_could_be_filled(): void
    {
        [$workcenter] = $this->workcenterWithOverride('2026-09-08', spots: 1); // nobody is eligible
        $week = PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $workcenter->id, 'planner_open' => true]);

        $this->generator()->generate($this->makeRun());

        $this->assertFalse($week->fresh()->planner_open);
    }

    public function test_a_run_keeps_the_open_flag_of_a_week_in_another_cycle(): void
    {
        [$workcenter] = $this->workcenterWithOverride('2026-09-08', spots: 1);
        $nextCycle = PublishedWeek::query()->create(['week_start' => '2026-09-21', 'workcenter_id' => $workcenter->id, 'planner_open' => true]);

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($nextCycle->fresh()->planner_open);
    }

    public function test_a_run_leaves_frozen_weeks_frozen_and_runs_without_any_open_week(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08', spots: 1);
        $this->eligibleEmployee($shift, '2026-09-08');
        $frozen = PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $workcenter->id]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertFalse($frozen->fresh()->planner_open);
        $this->assertSame(PlanGenerationRun::STATUS_DONE, $run->fresh()->status);
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

    // ── Spots ───────────────────────────────────────────────────────────

    private function datesAssigned(): array
    {
        return ShiftAssignment::query()->orderBy('date')->get()->map(fn (ShiftAssignment $a) => $a->date->toDateString())->all();
    }

    public function test_the_weekday_default_capacity_opens_spots_on_every_matching_weekday_of_the_cycle(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach($shift);
        WorkcenterShiftCapacity::query()->create(['workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => 2, 'spots' => 2]);
        foreach (range(1, 4) as $i) {
            $employee = $this->employee();
            $this->makeAvailable($employee, $shift, '2026-09-07'); // Monday: no capacity
            $this->makeAvailable($employee, $shift, '2026-09-08'); // Tuesday
        }

        $this->generator()->generate($this->makeRun());

        $this->assertSame(['2026-09-08', '2026-09-08', '2026-09-15', '2026-09-15'], $this->datesAssigned());
    }

    public function test_a_date_override_replaces_the_weekday_default_and_zero_closes_the_day(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach($shift);
        WorkcenterShiftCapacity::query()->create(['workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => 2, 'spots' => 2]);
        $this->openDay($workcenter, $shift, '2026-09-08', 1);
        $this->openDay($workcenter, $shift, '2026-09-15', 0);
        foreach (range(1, 4) as $i) {
            $this->eligibleEmployee($shift, '2026-09-08');
        }

        $this->generator()->generate($this->makeRun());

        $this->assertSame(['2026-09-08'], $this->datesAssigned());
    }

    public function test_the_cycle_includes_its_first_and_last_day_and_nothing_beside_them(): void
    {
        // The cycle runs from Monday 2026-09-07 to Sunday 2026-09-20. A default
        // capacity on Sundays and Mondays reaches 2026-09-06 and 2026-09-21 too,
        // the days just outside it, so only the cycle bounds keep those closed.
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach($shift);
        foreach ([1, 7] as $weekday) {
            WorkcenterShiftCapacity::query()->create(['workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => $weekday, 'spots' => 1]);
        }
        $employee = $this->employee();
        $this->makeAvailable($employee, $shift, '2026-09-07'); // Monday
        $this->makeAvailable($employee, $shift, '2026-09-20'); // Sunday

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(['2026-09-07', '2026-09-13', '2026-09-14', '2026-09-20'], $this->datesAssigned());
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_an_archived_workcenter_is_not_planned(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08');
        $this->eligibleEmployee($shift, '2026-09-08');
        $workcenter->update(['archived_at' => now()]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame([], $run->refresh()->unfulfilled);

        $workcenter->update(['archived_at' => null]);
        $this->generator()->generate($this->makeRun());

        $this->assertSame(1, ShiftAssignment::count());
    }

    public function test_a_cell_with_more_spots_than_candidates_is_partly_filled_and_reported(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08', spots: 2);
        $this->eligibleEmployee($shift, '2026-09-08');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(1, ShiftAssignment::count());
        $this->assertCount(1, $run->refresh()->unfulfilled);
    }

    /** A capped employee (cap 8h) who could take the open cell on 2026-09-08 if nothing else counted. */
    private function cappedEmployeeWithOpenCell(): array
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08'); // 8h
        $employee = $this->employee(['weekly_hours' => 4]);
        $this->makeAvailable($employee, $shift, '2026-09-08');
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        return [$workcenter, $shift, $employee];
    }

    public function test_only_hours_inside_the_cycle_count_toward_the_hour_cap(): void
    {
        [$workcenter, $shift, $employee] = $this->cappedEmployeeWithOpenCell();
        // The day before the cycle and the day after it, 8h each. Fixed, so a
        // planner that counted them could not move them out of the way.
        foreach (['2026-09-06', '2026-09-21'] as $date) {
            ShiftAssignment::factory()->create([
                'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
                'date' => $date, 'fixed' => true,
            ]);
        }

        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $employee->id, 'date' => '2026-09-08']);
    }

    public function test_hours_on_the_last_day_of_the_cycle_count_toward_the_hour_cap(): void
    {
        [$workcenter, $shift, $employee] = $this->cappedEmployeeWithOpenCell();
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
            'date' => '2026-09-20', 'fixed' => true, // fixed, or the planner would move it to the open cell
        ]);

        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $employee->id, 'date' => '2026-09-08']);
    }
}
