<?php

namespace Tests\Feature\Planning;

use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cap is `weekly_hours × 2` over the two-week cycle, for every hour the
 * employee holds in it: planned, fixed, and published. Each shift counts at its
 * duration rounded to a 4-hour block. Most shifts below last 8h.
 */
class MaxHoursPerWeekRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    private function shift(): Shift
    {
        return Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
    }

    // ── Hard ────────────────────────────────────────────────────────────

    public function test_a_hard_cap_excludes_a_candidate_whose_shift_would_exceed_it(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift();
        $this->openCell($workcenter, $shift, '2026-09-08');
        // Created first, so the planner picks it when no rule exists. Cap 6h < 8h shift.
        $capped = $this->employee(['weekly_hours' => 3]);
        $this->makeAvailable($capped, $shift, '2026-09-08');
        $uncapped = $this->employee(['weekly_hours' => 40]);
        $this->makeAvailable($uncapped, $shift, '2026-09-08');

        $this->generator()->generate($this->makeRun());
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $capped->id]);

        ShiftAssignment::query()->delete();
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $uncapped->id]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $capped->id]);
    }

    public function test_a_hard_cap_leaves_the_spot_open_when_only_a_capped_candidate_exists(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $capped = $this->employee(['weekly_hours' => 3]); // cap 6h < 8h shift
        $this->makeAvailable($capped, $shift, '2026-09-08');
        $rule = PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame('no_eligible_employee', $run->refresh()->unfulfilled[0]['reason']);

        $rule->delete();
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $capped->id]);
    }

    public function test_a_hard_cap_allows_hours_up_to_exactly_the_limit_and_no_further(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift();
        $this->openCell($workcenter, $shift, '2026-09-08');
        $this->openDay($workcenter, $shift, '2026-09-09');
        $employee = $this->employee(['weekly_hours' => 4]); // cap 8h: one shift exactly
        $this->makeAvailable($employee, $shift, '2026-09-08');
        $this->makeAvailable($employee, $shift, '2026-09-09');

        $this->generator()->generate($this->makeRun());
        $this->assertSame(2, ShiftAssignment::count(), 'control: without the rule both shifts are assigned');

        ShiftAssignment::query()->delete();
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);
        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(1, ShiftAssignment::count());
        $unfulfilled = $run->refresh()->unfulfilled;
        $this->assertCount(1, $unfulfilled);
        $this->assertSame('hard_cap_reached', $unfulfilled[0]['reason']);
    }

    public function test_a_hard_cap_counts_hours_across_workcenters_and_both_weeks_of_the_cycle(): void
    {
        $shift = $this->shift();
        $first = Workcenter::factory()->create();
        $second = Workcenter::factory()->create();
        $this->openCell($first, $shift, '2026-09-08'); // week 1
        $this->openDay($first, $shift, '2026-09-15'); // week 2
        $this->openCell($second, $shift, '2026-09-09'); // week 1, another workcenter
        $employee = $this->employee(['weekly_hours' => 8]); // cap 16h: two shifts
        $this->makeAvailable($employee, $shift, '2026-09-08'); // Tuesday, covers 09-15 too
        $this->makeAvailable($employee, $shift, '2026-09-09');

        $this->generator()->generate($this->makeRun());
        $this->assertSame(3, ShiftAssignment::count(), 'control: without the rule all three are assigned');

        ShiftAssignment::query()->delete();
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);
        $this->generator()->generate($this->makeRun());

        $this->assertSame(2, ShiftAssignment::count());
        $this->assertSame(1, ShiftAssignment::query()->whereBetween('date', ['2026-09-07', '2026-09-13'])->count());
        $this->assertSame(1, ShiftAssignment::query()->whereBetween('date', ['2026-09-14', '2026-09-20'])->count());
    }

    public function test_a_hard_cap_limits_the_second_week_to_weekly_hours_plus_four(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift();
        $this->openCell($workcenter, $shift, '2026-09-15');
        $this->openDay($workcenter, $shift, '2026-09-16');
        $employee = $this->employee(['weekly_hours' => 8]); // weekly cap 12h: one 8h shift
        $this->makeAvailable($employee, $shift, '2026-09-15');
        $this->makeAvailable($employee, $shift, '2026-09-16');
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $this->generator()->generate($this->makeRun());

        $this->assertSame(1, ShiftAssignment::count());
    }

    public function test_a_hard_cap_allows_exactly_four_extra_hours_in_one_week(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift();
        $this->openCell($workcenter, $shift, '2026-09-08');
        $this->openDay($workcenter, $shift, '2026-09-09');
        $this->openDay($workcenter, $shift, '2026-09-10');
        $employee = $this->employee(['weekly_hours' => 12]); // weekly cap 16h: two 8h shifts
        foreach (['2026-09-08', '2026-09-09', '2026-09-10'] as $date) {
            $this->makeAvailable($employee, $shift, $date);
        }
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $this->generator()->generate($this->makeRun());

        $this->assertSame(2, ShiftAssignment::count());
    }

    public function test_a_hard_cap_counts_a_slightly_long_shift_as_a_four_hour_block(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:15']); // 8.25h counts as 8
        $this->openCell($workcenter, $shift, '2026-09-08'); // week 1
        $this->openDay($workcenter, $shift, '2026-09-15'); // week 2
        $this->openDay($workcenter, $shift, '2026-09-09');
        $employee = $this->employee(['weekly_hours' => 8]); // cap 16h: 16.5 real hours, 16 counted
        $this->makeAvailable($employee, $shift, '2026-09-08');
        $this->makeAvailable($employee, $shift, '2026-09-09');
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $this->generator()->generate($this->makeRun());

        $this->assertSame(2, ShiftAssignment::count());
    }

    public function test_a_hard_cap_counts_a_nine_hour_evening_shift_as_eight(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '23:00']);
        foreach (['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-15'] as $i => $date) {
            $i === 0 ? $this->openCell($workcenter, $shift, $date) : $this->openDay($workcenter, $shift, $date);
        }
        $employee = $this->employee(['weekly_hours' => 20]); // weekly cap 24h: 3 × 9 = 27 real hours, 24 counted
        foreach (['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'] as $date) {
            $this->makeAvailable($employee, $shift, $date);
        }
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $this->generator()->generate($this->makeRun());

        $this->assertSame(4, ShiftAssignment::count());
        $this->assertSame(3, ShiftAssignment::query()->whereBetween('date', ['2026-09-07', '2026-09-13'])->count());
    }

    public function test_a_hard_weekly_cap_keeps_fixed_excess_assignments_and_blocks_an_addition(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift();
        $this->openCell($workcenter, $shift, '2026-09-08');
        $this->openDay($workcenter, $shift, '2026-09-09');
        $this->openDay($workcenter, $shift, '2026-09-10');
        $employee = $this->employee(['weekly_hours' => 8]); // weekly cap 12h
        foreach (['2026-09-08', '2026-09-09', '2026-09-10'] as $date) {
            $this->makeAvailable($employee, $shift, $date);
        }
        foreach (['2026-09-08', '2026-09-09'] as $date) {
            ShiftAssignment::factory()->create([
                'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
                'date' => $date, 'fixed' => true,
            ]);
        }
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $this->generator()->generate($this->makeRun());

        $this->assertSame(2, ShiftAssignment::count());
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $employee->id, 'date' => '2026-09-08', 'fixed' => true]);
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $employee->id, 'date' => '2026-09-09', 'fixed' => true]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $employee->id, 'date' => '2026-09-10']);
    }

    public function test_a_hard_cap_counts_the_hours_of_a_fixed_assignment(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift();
        $this->openCell($workcenter, $shift, '2026-09-08');
        $this->openDay($workcenter, $shift, '2026-09-09');
        $employee = $this->employee(['weekly_hours' => 4]); // cap 8h
        $this->makeAvailable($employee, $shift, '2026-09-08');
        $this->makeAvailable($employee, $shift, '2026-09-09');
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
            'date' => '2026-09-08', 'fixed' => true,
        ]);

        $this->generator()->generate($this->makeRun());
        $this->assertSame(2, ShiftAssignment::count(), 'control: without the rule the open day is assigned');

        ShiftAssignment::query()->where('date', '2026-09-09')->delete();
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);
        $this->generator()->generate($this->makeRun());

        $this->assertSame(1, ShiftAssignment::count());
    }

    // ── Soft ────────────────────────────────────────────────────────────

    public function test_a_soft_cap_prefers_the_candidate_who_stays_under_it(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift();
        $this->openCell($workcenter, $shift, '2026-09-08');
        // Created first, so the planner picks it when no rule exists. Cap 6h < 8h shift.
        $capped = $this->employee(['weekly_hours' => 3]);
        $this->makeAvailable($capped, $shift, '2026-09-08');
        $uncapped = $this->employee(['weekly_hours' => 40]);
        $this->makeAvailable($uncapped, $shift, '2026-09-08');

        $this->generator()->generate($this->makeRun());
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $capped->id]);

        ShiftAssignment::query()->delete();
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'soft', 'severity' => 5]);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $uncapped->id]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $capped->id]);
    }

    public function test_a_soft_cap_is_broken_when_that_is_the_only_way_to_fill_the_spot(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $capped = $this->employee(['weekly_hours' => 3]);
        $this->makeAvailable($capped, $shift, '2026-09-08');
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'soft', 'severity' => 10]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $capped->id]);
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_a_soft_cap_costs_its_severity_for_each_hour_over_the_cap(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift();
        $this->openCell($workcenter, $shift, '2026-09-08');
        // Over the cap by 2h (cap 6h, shift 8h).
        $over = $this->employee(['weekly_hours' => 3]);
        $this->makeAvailable($over, $shift, '2026-09-08');
        // Within the cap, but on a not-preferred cell that costs 5.
        $notPreferred = $this->employee(['weekly_hours' => 40]);
        $this->makeAvailable($notPreferred, $shift, '2026-09-08', 'not_preferred');
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);
        $hours = PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'soft', 'severity' => 2]);

        // 2h over × severity 2 = 4, below the not-preferred cost of 5: going over wins.
        $this->generator()->generate($this->makeRun());
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $over->id]);

        // 2h over × severity 3 = 6, above 5: the not-preferred cell wins. A flat
        // cost of 3 per violation would still pick the employee over the cap.
        ShiftAssignment::query()->delete();
        $hours->update(['severity' => 3]);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $notPreferred->id]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $over->id]);
    }

    public function test_a_soft_cap_counts_a_nine_hour_shift_as_eight(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '23:00']);
        $this->openCell($workcenter, $shift, '2026-09-08');
        // Cap 8h. The 9h shift counts as 8, so it is not over the cap. Created first.
        $small = $this->employee(['weekly_hours' => 4]);
        $this->makeAvailable($small, $shift, '2026-09-08');
        // Within the cap, but on a not-preferred cell that costs 5.
        $notPreferred = $this->employee(['weekly_hours' => 40]);
        $this->makeAvailable($notPreferred, $shift, '2026-09-08', 'not_preferred');
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);
        // Real hours would cost 1h × 10 = 10 and make the not-preferred cell win.
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'soft', 'severity' => 10]);

        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $small->id]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $notPreferred->id]);
    }
}
