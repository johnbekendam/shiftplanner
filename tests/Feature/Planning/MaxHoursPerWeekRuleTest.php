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
 * employee holds in it: planned, fixed, and published. All shifts below last 8h.
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
}
