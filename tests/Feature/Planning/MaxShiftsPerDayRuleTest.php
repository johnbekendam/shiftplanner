<?php

namespace Tests\Feature\Planning;

use App\Models\Employee;
use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cap counts every shift an employee holds on one date, across workcenters.
 * The shifts below do not overlap, so only this rule can stop a second one.
 */
class MaxShiftsPerDayRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    private Workcenter $workcenter;

    private Shift $early;

    private Shift $mid;

    private Shift $late;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workcenter = Workcenter::factory()->create();
        $this->early = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '08:00']);
        $this->mid = Shift::factory()->create(['start_time' => '08:00', 'end_time' => '10:00']);
        $this->late = Shift::factory()->create(['start_time' => '10:00', 'end_time' => '12:00']);
    }

    private function hold(Employee $employee, Shift $shift, string $date, ?Workcenter $workcenter = null): void
    {
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => ($workcenter ?? $this->workcenter)->id,
            'shift_id' => $shift->id, 'date' => $date, 'fixed' => true,
        ]);
    }

    /**
     * $busy holds two shifts on Tuesday, $free holds two on Wednesday, so both
     * carry the same load. $busy is created first: with no rule the planner
     * picks it for the open late shift on Tuesday.
     *
     * @return array{Employee, Employee}
     */
    private function tuesdayScenario(): array
    {
        $this->openCell($this->workcenter, $this->late, '2026-09-08');
        $busy = $this->employee();
        $free = $this->employee();
        foreach ([$busy, $free] as $employee) {
            $this->makeAvailable($employee, $this->late, '2026-09-08');
        }
        foreach ([$this->early, $this->mid] as $shift) {
            $this->hold($busy, $shift, '2026-09-08');
            $this->hold($free, $shift, '2026-09-09');
        }

        return [$busy, $free];
    }

    // ── Hard ────────────────────────────────────────────────────────────

    public function test_a_hard_cap_excludes_a_candidate_who_already_holds_the_maximum_that_day(): void
    {
        [$busy, $free] = $this->tuesdayScenario();

        $this->generator()->generate($this->makeRun());
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $busy->id, 'shift_id' => $this->late->id]);

        ShiftAssignment::query()->where('shift_id', $this->late->id)->delete();
        PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'hard', 'config' => ['value' => 2]]);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $free->id, 'shift_id' => $this->late->id]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $busy->id, 'shift_id' => $this->late->id]);
    }

    public function test_a_hard_cap_allows_shifts_up_to_exactly_the_maximum_and_no_further(): void
    {
        $this->openCell($this->workcenter, $this->mid, '2026-09-08');
        $this->openDay($this->workcenter, $this->late, '2026-09-08');
        $this->workcenter->shifts()->attach($this->late);
        $employee = $this->employee();
        $this->makeAvailable($employee, $this->mid, '2026-09-08');
        $this->makeAvailable($employee, $this->late, '2026-09-08');
        $this->hold($employee, $this->early, '2026-09-08');

        $this->generator()->generate($this->makeRun());
        $this->assertSame(3, ShiftAssignment::count(), 'control: without the rule all three shifts are held');

        ShiftAssignment::query()->where('fixed', false)->delete();
        PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'hard', 'config' => ['value' => 2]]);
        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(2, ShiftAssignment::count(), 'the fixed shift plus exactly one more');
        $unfulfilled = $run->refresh()->unfulfilled;
        $this->assertCount(1, $unfulfilled);
        $this->assertSame('hard_cap_reached', $unfulfilled[0]['reason']);
    }

    public function test_a_hard_cap_applies_to_each_day_separately(): void
    {
        $this->openCell($this->workcenter, $this->late, '2026-09-08');
        $this->openDay($this->workcenter, $this->late, '2026-09-09');
        $employee = $this->employee();
        $this->makeAvailable($employee, $this->late, '2026-09-08');
        $this->makeAvailable($employee, $this->late, '2026-09-09');
        PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'hard', 'config' => ['value' => 1]]);

        $this->generator()->generate($this->makeRun());

        $this->assertSame(2, ShiftAssignment::count());
    }

    public function test_a_hard_cap_counts_the_shifts_an_employee_holds_in_other_workcenters(): void
    {
        $other = Workcenter::factory()->create();
        $this->openCell($this->workcenter, $this->late, '2026-09-08');
        $employee = $this->employee();
        $this->makeAvailable($employee, $this->late, '2026-09-08');
        $this->hold($employee, $this->early, '2026-09-08', $other);

        $this->generator()->generate($this->makeRun());
        $this->assertSame(2, ShiftAssignment::count(), 'control: without the rule the open shift is assigned');

        ShiftAssignment::query()->where('fixed', false)->delete();
        PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'hard', 'config' => ['value' => 1]]);
        $this->generator()->generate($this->makeRun());

        $this->assertSame(1, ShiftAssignment::count());
    }

    // ── Soft ────────────────────────────────────────────────────────────

    public function test_a_soft_cap_prefers_the_candidate_who_stays_under_it(): void
    {
        [$busy, $free] = $this->tuesdayScenario();

        $this->generator()->generate($this->makeRun());
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $busy->id, 'shift_id' => $this->late->id]);

        ShiftAssignment::query()->where('shift_id', $this->late->id)->delete();
        PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'soft', 'severity' => 10, 'config' => ['value' => 2]]);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $free->id, 'shift_id' => $this->late->id]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $busy->id, 'shift_id' => $this->late->id]);
    }

    public function test_a_soft_cap_is_broken_when_that_is_the_only_way_to_fill_the_spot(): void
    {
        $this->openCell($this->workcenter, $this->late, '2026-09-08');
        $employee = $this->employee();
        $this->makeAvailable($employee, $this->late, '2026-09-08');
        $this->hold($employee, $this->early, '2026-09-08');
        PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'soft', 'severity' => 10, 'config' => ['value' => 1]]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $employee->id, 'shift_id' => $this->late->id]);
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_a_soft_cap_costs_its_severity_for_each_shift_over_the_cap(): void
    {
        $this->openCell($this->workcenter, $this->late, '2026-09-08');
        // Already 1 over the cap of 1, so one more shift adds one more unit over.
        $over = $this->employee();
        $this->makeAvailable($over, $this->late, '2026-09-08');
        $this->hold($over, $this->early, '2026-09-08');
        $this->hold($over, $this->mid, '2026-09-08');
        // Free of the cap, but on a not-preferred cell that costs 2.
        $notPreferred = $this->employee();
        $this->makeAvailable($notPreferred, $this->late, '2026-09-08', 'not_preferred');
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 2]);
        $cap = PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'soft', 'severity' => 1, 'config' => ['value' => 1]]);

        // One more unit over costs 1, below the not-preferred cost of 2: going over wins.
        $this->generator()->generate($this->makeRun());
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $over->id, 'shift_id' => $this->late->id]);

        // One more unit over costs 3, above 2: the not-preferred cell wins. A flat
        // cost per over-the-cap day would add nothing here, since the day is
        // already over, and would keep picking the employee over the cap.
        ShiftAssignment::query()->where('shift_id', $this->late->id)->delete();
        $cap->update(['severity' => 3]);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $notPreferred->id, 'shift_id' => $this->late->id]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $over->id, 'shift_id' => $this->late->id]);
    }
}
