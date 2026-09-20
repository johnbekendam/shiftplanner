<?php

namespace Tests\Feature\Planning;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The objective is lexicographic: coverage, then the highest hour total
 * (`equal_workload`), then the severity-weighted soft costs. Construction
 * fills cells most-constrained first, then the optimizer improves the result
 * with fill, relocate, substitute, and swap moves. Each scenario is built so
 * that exactly one of these can reach the expected result.
 */
class ObjectiveTiersTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    private const TUESDAY = '2026-09-08';

    private const WEDNESDAY = '2026-09-09';

    private const THURSDAY = '2026-09-10';

    private function shift(string $start, string $end): Shift
    {
        return Shift::factory()->create(['start_time' => $start, 'end_time' => $end]);
    }

    private function hold(Employee $employee, Workcenter $workcenter, Shift $shift, string $date, bool $fixed = false): ShiftAssignment
    {
        return ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id, 'date' => $date, 'fixed' => $fixed,
        ]);
    }

    private function holds(Employee $employee, Workcenter $workcenter, string $date): bool
    {
        return ShiftAssignment::query()
            ->where(['employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'date' => $date])
            ->exists();
    }

    // ── Construction ────────────────────────────────────────────────────

    public function test_construction_fills_the_most_constrained_cell_first(): void
    {
        // Both cells are on the same shift and day, so nobody can fill both.
        // The first cell has two candidates, the second only the first employee.
        // Filling the roomy cell first gives it to the first employee and strands
        // the tight one, and the optimizer cannot repair that: every move ties.
        $roomy = Workcenter::factory()->create();
        $tight = Workcenter::factory()->create();
        $shift = $this->shift('06:00', '14:00');
        $this->openCell($roomy, $shift, self::TUESDAY);
        $this->openCell($tight, $shift, self::TUESDAY);
        $both = $this->eligibleEmployee($shift, self::TUESDAY);
        $roomyOnly = $this->eligibleEmployee($shift, self::TUESDAY);
        $roomyOnly->workcenters()->attach($roomy->id, ['mode' => 'hard']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertTrue($this->holds($both, $tight, self::TUESDAY));
        $this->assertTrue($this->holds($roomyOnly, $roomy, self::TUESDAY));
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_construction_gives_a_spot_to_the_least_loaded_candidate(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift('06:00', '14:00');
        $this->openCell($workcenter, $shift, self::TUESDAY);
        // Created first, so it would win a tie, but it already holds 8h.
        $loaded = $this->eligibleEmployee($shift, self::TUESDAY);
        $free = $this->eligibleEmployee($shift, self::TUESDAY);
        $this->hold($loaded, $workcenter, $shift, self::WEDNESDAY, fixed: true);

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($free, $workcenter, self::TUESDAY));
        $this->assertFalse($this->holds($loaded, $workcenter, self::TUESDAY));
    }

    public function test_construction_breaks_a_tie_by_the_order_of_the_employees(): void
    {
        // Other tests rely on this: the employee created first wins a tie.
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift('06:00', '14:00');
        $this->openCell($workcenter, $shift, self::TUESDAY);
        $first = $this->eligibleEmployee($shift, self::TUESDAY);
        $this->eligibleEmployee($shift, self::TUESDAY);

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($first, $workcenter, self::TUESDAY));
    }

    // ── Tiers ───────────────────────────────────────────────────────────

    public function test_coverage_outweighs_fairness_when_a_fill_raises_the_highest_total(): void
    {
        // P holds 24h: two fixed shifts and a movable one on Tuesday in the first
        // workcenter. The second workcenter has an open Tuesday spot at the same
        // time, and only P can fill it, but not while P holds the first one.
        // Moving the first shift to Q lowers the highest total from 24h to 16h.
        // Only then can P fill the open spot, which raises the highest total to
        // 24h again. The spot still has to be filled.
        $first = Workcenter::factory()->create();
        $second = Workcenter::factory()->create();
        $shift = $this->shift('06:00', '14:00');
        $this->openCell($first, $shift, self::TUESDAY);
        $this->openCell($second, $shift, self::TUESDAY);
        $p = $this->eligibleEmployee($shift, self::TUESDAY);
        $q = $this->eligibleEmployee($shift, self::TUESDAY);
        $q->workcenters()->attach($first->id, ['mode' => 'hard']);
        $this->hold($p, $first, $shift, self::WEDNESDAY, fixed: true);
        $this->hold($p, $first, $shift, self::THURSDAY, fixed: true);
        $this->hold($p, $first, $shift, self::TUESDAY);

        // Control: without the rule nothing improves, so the spot stays open.
        $run = $this->makeRun();
        $this->generator()->generate($run);
        $this->assertFalse($this->holds($p, $second, self::TUESDAY));
        $this->assertCount(1, $run->refresh()->unfulfilled);

        PlanningRule::create(['type' => 'equal_workload']);
        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertTrue($this->holds($q, $first, self::TUESDAY));
        $this->assertTrue($this->holds($p, $second, self::TUESDAY));
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_fairness_outweighs_soft_costs(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift('06:00', '14:00');
        $this->openCell($workcenter, $shift, self::TUESDAY);
        $this->openDay($workcenter, $shift, self::WEDNESDAY);
        $a = $this->eligibleEmployee($shift, self::TUESDAY);
        $this->makeAvailable($a, $shift, self::WEDNESDAY);
        // The second employee is on a not-preferred cell for both days, which costs 9 each.
        $c = $this->employee();
        $this->makeAvailable($c, $shift, self::TUESDAY, 'not_preferred');
        $this->makeAvailable($c, $shift, self::WEDNESDAY, 'not_preferred');
        $this->hold($a, $workcenter, $shift, self::TUESDAY);
        $this->hold($a, $workcenter, $shift, self::WEDNESDAY);
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 9]);

        // Control: the soft cost alone keeps both shifts with the first employee.
        $this->generator()->generate($this->makeRun());
        $this->assertSame(0, ShiftAssignment::query()->where('employee_id', $c->id)->count());

        PlanningRule::create(['type' => 'equal_workload']);
        $this->generator()->generate($this->makeRun());

        $this->assertSame(1, ShiftAssignment::query()->where('employee_id', $c->id)->count());
        $this->assertSame(1, ShiftAssignment::query()->where('employee_id', $a->id)->count());
    }

    // ── Moves ───────────────────────────────────────────────────────────

    public function test_the_optimizer_relocates_an_assignment_to_a_better_open_spot(): void
    {
        // The two shifts overlap, so the employee cannot fill the open spot as
        // well. Only a relocation helps: Early is a not-preferred shift for them,
        // Mid is not.
        $earlySpot = Workcenter::factory()->create();
        $midSpot = Workcenter::factory()->create();
        $early = $this->shift('06:00', '14:00');
        $mid = $this->shift('10:00', '18:00');
        $this->openCell($earlySpot, $early, self::TUESDAY);
        $this->openCell($midSpot, $mid, self::TUESDAY);
        $employee = $this->employee();
        $this->makeAvailable($employee, $early, self::TUESDAY, 'not_preferred');
        $this->makeAvailable($employee, $mid, self::TUESDAY);
        $this->hold($employee, $earlySpot, $early, self::TUESDAY);

        // Control: without the rule the employee stays where they are.
        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->holds($employee, $earlySpot, self::TUESDAY));

        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);
        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertTrue($this->holds($employee, $midSpot, self::TUESDAY));
        $this->assertFalse($this->holds($employee, $earlySpot, self::TUESDAY));
        $this->assertCount(1, $run->refresh()->unfulfilled);
    }

    public function test_the_optimizer_swaps_two_employees_when_no_other_move_helps(): void
    {
        // The shifts overlap, so neither employee can take the other's shift on
        // top of their own, and both spots are full. Only a swap removes the
        // not-preferred cost on both sides.
        $earlySpot = Workcenter::factory()->create();
        $midSpot = Workcenter::factory()->create();
        $early = $this->shift('06:00', '14:00');
        $mid = $this->shift('10:00', '18:00');
        $this->openCell($earlySpot, $early, self::TUESDAY);
        $this->openCell($midSpot, $mid, self::TUESDAY);
        $a = $this->employee();
        $this->makeAvailable($a, $early, self::TUESDAY, 'not_preferred');
        $this->makeAvailable($a, $mid, self::TUESDAY);
        $b = $this->employee();
        $this->makeAvailable($b, $early, self::TUESDAY);
        $this->makeAvailable($b, $mid, self::TUESDAY, 'not_preferred');
        $this->hold($a, $earlySpot, $early, self::TUESDAY);
        $this->hold($b, $midSpot, $mid, self::TUESDAY);

        // Control: without the rule nobody moves.
        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->holds($a, $earlySpot, self::TUESDAY));

        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($a, $midSpot, self::TUESDAY));
        $this->assertTrue($this->holds($b, $earlySpot, self::TUESDAY));
    }

    public function test_the_optimizer_never_moves_work_to_an_employee_who_is_not_eligible(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = $this->shift('06:00', '14:00');
        $this->openCell($workcenter, $shift, self::TUESDAY);
        $this->openDay($workcenter, $shift, self::WEDNESDAY);
        $a = $this->eligibleEmployee($shift, self::TUESDAY);
        $this->makeAvailable($a, $shift, self::WEDNESDAY);
        $c = $this->eligibleEmployee($shift, self::TUESDAY);
        $this->makeAvailable($c, $shift, self::WEDNESDAY);
        $this->hold($a, $workcenter, $shift, self::TUESDAY);
        $this->hold($a, $workcenter, $shift, self::WEDNESDAY);
        PlanningRule::create(['type' => 'equal_workload']);
        $holiday = EmployeeHoliday::query()->create(['employee_id' => $c->id, 'start_date' => self::TUESDAY, 'end_date' => self::WEDNESDAY]);

        $this->generator()->generate($this->makeRun());
        $this->assertSame(2, ShiftAssignment::query()->where('employee_id', $a->id)->count(), 'the holiday blocks the move');

        $holiday->delete();
        $this->generator()->generate($this->makeRun());

        $this->assertSame(1, ShiftAssignment::query()->where('employee_id', $c->id)->count(), 'control: without the holiday the work moves');
    }

    public function test_a_relocation_only_goes_to_a_spot_where_the_employee_is_eligible(): void
    {
        $tuesdaySpot = Workcenter::factory()->create();
        $wednesdaySpot = Workcenter::factory()->create();
        $shift = $this->shift('06:00', '14:00');
        $this->openCell($tuesdaySpot, $shift, self::TUESDAY);
        $this->openCell($wednesdaySpot, $shift, self::WEDNESDAY);
        // Tuesday is a not-preferred cell for the employee, Wednesday is not. Moving
        // there would lower the cost, but they are on holiday on Wednesday.
        $employee = $this->employee();
        $this->makeAvailable($employee, $shift, self::TUESDAY, 'not_preferred');
        $this->makeAvailable($employee, $shift, self::WEDNESDAY);
        $holiday = EmployeeHoliday::query()->create(['employee_id' => $employee->id, 'start_date' => self::WEDNESDAY, 'end_date' => self::WEDNESDAY]);
        $this->hold($employee, $tuesdaySpot, $shift, self::TUESDAY);
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($employee, $tuesdaySpot, self::TUESDAY));
        $this->assertFalse($this->holds($employee, $wednesdaySpot, self::WEDNESDAY));

        // Control: without the holiday the employee takes the Wednesday spot too.
        $holiday->delete();
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($employee, $wednesdaySpot, self::WEDNESDAY));
    }

    public function test_a_swap_only_happens_when_both_employees_are_eligible_for_the_other_shift(): void
    {
        $earlySpot = Workcenter::factory()->create();
        $midSpot = Workcenter::factory()->create();
        $early = $this->shift('06:00', '14:00');
        $mid = $this->shift('10:00', '18:00');
        $this->openCell($earlySpot, $early, self::TUESDAY);
        $this->openCell($midSpot, $mid, self::TUESDAY);
        $a = $this->employee();
        $this->makeAvailable($a, $early, self::TUESDAY, 'not_preferred');
        $this->makeAvailable($a, $mid, self::TUESDAY);
        // The second employee never marked the early shift as available.
        $b = $this->employee();
        $this->makeAvailable($b, $mid, self::TUESDAY, 'not_preferred');
        $this->hold($a, $earlySpot, $early, self::TUESDAY);
        $this->hold($b, $midSpot, $mid, self::TUESDAY);
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($a, $earlySpot, self::TUESDAY));
        $this->assertTrue($this->holds($b, $midSpot, self::TUESDAY));

        // Control: once the second employee is available for the early shift, they swap.
        $this->makeAvailable($b, $early, self::TUESDAY);
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($a, $midSpot, self::TUESDAY));
        $this->assertTrue($this->holds($b, $earlySpot, self::TUESDAY));
    }
}
