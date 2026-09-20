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
 * The pair rule compares the same weekday 7 days earlier. If the earlier day
 * holds exactly one pair member, the opposite member is preferred. Every
 * scenario starts from a state where the greedy step repeats the earlier
 * shift, so only the rule can change the result. The pair is Early and Late.
 */
class AlternatingShiftPairRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    private const PREVIOUS_MONDAY = '2026-08-31';

    private const MONDAY = '2026-09-07';

    private Workcenter $workcenter;

    private Shift $early;

    private Shift $late;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workcenter = Workcenter::factory()->create();
        $this->early = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->late = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
    }

    private function pair(int $severity = 10, ?Shift $first = null, ?Shift $second = null): PlanningRule
    {
        return PlanningRule::create([
            'type' => 'alternating_shift_pair', 'mode' => 'soft', 'severity' => $severity,
            'config' => ['first_shift_id' => ($first ?? $this->early)->id, 'second_shift_id' => ($second ?? $this->late)->id],
        ]);
    }

    /** A confirmed employee available on Mondays for Early and Late. */
    private function worker(): Employee
    {
        $employee = $this->employee();
        $this->makeAvailable($employee, $this->early, self::MONDAY);
        $this->makeAvailable($employee, $this->late, self::MONDAY);

        return $employee;
    }

    private function hold(Employee $employee, Shift $shift, string $date, bool $fixed = false): void
    {
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $this->workcenter->id,
            'shift_id' => $shift->id, 'date' => $date, 'fixed' => $fixed,
        ]);
    }

    private function holds(Employee $employee, Shift $shift, string $date): bool
    {
        return ShiftAssignment::query()
            ->where(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'date' => $date])
            ->exists();
    }

    /**
     * A fixed 8h shift on a day no test compares, so the employee starts the
     * cycle with more hours than an employee without one.
     */
    private function weigh(Employee $employee): void
    {
        $this->hold($employee, Shift::factory()->create(['start_time' => '00:00', 'end_time' => '08:00']), '2026-09-10', fixed: true);
    }

    /** True when $employee holds $repeated on $date but not $opposite. */
    private function repeatsAlone(Employee $employee, Shift $repeated, Shift $opposite, string $date): bool
    {
        return $this->holds($employee, $repeated, $date) && ! $this->holds($employee, $opposite, $date);
    }

    /** Clears what the planner made in the cycle and keeps the assignments before it. */
    private function clearCycle(): void
    {
        ShiftAssignment::query()->where('fixed', false)->where('date', '>=', self::MONDAY)->delete();
    }

    // ── Alternation ─────────────────────────────────────────────────────

    public function test_the_opposite_shift_is_preferred_to_the_one_worked_a_week_earlier(): void
    {
        $this->openCell($this->workcenter, $this->early, self::MONDAY);
        $this->openCell($this->workcenter, $this->late, self::MONDAY);
        $first = $this->worker();
        $second = $this->worker();
        $this->hold($first, $this->early, self::PREVIOUS_MONDAY);

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->holds($first, $this->early, self::MONDAY), 'control: without the rule the first employee repeats Early');

        $this->clearCycle();
        $this->pair();
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($first, $this->late, self::MONDAY));
        $this->assertFalse($this->holds($first, $this->early, self::MONDAY));
        $this->assertTrue($this->holds($second, $this->early, self::MONDAY));
    }

    public function test_the_rule_works_from_either_member_of_the_pair(): void
    {
        $this->openCell($this->workcenter, $this->early, self::MONDAY);
        $this->openCell($this->workcenter, $this->late, self::MONDAY);
        // The first employee starts heavier, so the planner gives them Late, the
        // shift they worked a week earlier.
        $first = $this->worker();
        $second = $this->worker();
        $this->weigh($first);
        $this->hold($first, $this->late, self::PREVIOUS_MONDAY);

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->holds($first, $this->late, self::MONDAY), 'control: without the rule the first employee repeats Late');

        $this->clearCycle();
        $this->pair();
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($first, $this->early, self::MONDAY));
        $this->assertTrue($this->holds($second, $this->late, self::MONDAY));
    }

    public function test_the_second_week_compares_with_the_generated_first_week(): void
    {
        $this->openCell($this->workcenter, $this->early, self::MONDAY); // week 1: only Early
        $this->openDay($this->workcenter, $this->early, '2026-09-14'); // week 2: Early and Late
        $this->openCell($this->workcenter, $this->late, '2026-09-14');
        $first = $this->worker();
        $second = $this->worker();
        // The second employee is on holiday in week 1, so the planner has to give
        // the first employee that Early shift. A fixed 8h shift on Thursday gives
        // the second employee the same load, so the first still wins the tie in week 2.
        EmployeeHoliday::query()->create(['employee_id' => $second->id, 'start_date' => self::MONDAY, 'end_date' => self::MONDAY]);
        $this->weigh($second);

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->holds($first, $this->early, self::MONDAY));
        $this->assertTrue($this->holds($first, $this->early, '2026-09-14'), 'control: without the rule the first employee repeats Early');

        $this->clearCycle();
        $this->pair();
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($first, $this->early, self::MONDAY));
        $this->assertTrue($this->holds($first, $this->late, '2026-09-14'));
        $this->assertTrue($this->holds($second, $this->early, '2026-09-14'));
    }

    public function test_an_unrelated_shift_on_the_earlier_day_does_not_change_the_comparison(): void
    {
        $unrelated = Shift::factory()->create(['start_time' => '03:00', 'end_time' => '05:00']);
        $this->openCell($this->workcenter, $this->early, self::MONDAY);
        $this->openCell($this->workcenter, $this->late, self::MONDAY);
        $first = $this->worker();
        $second = $this->worker();
        $this->hold($first, $this->early, self::PREVIOUS_MONDAY);
        $this->hold($first, $unrelated, self::PREVIOUS_MONDAY);
        $this->pair();

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($first, $this->late, self::MONDAY));
        $this->assertTrue($this->holds($second, $this->early, self::MONDAY));
    }

    public function test_two_pairs_each_apply_to_their_own_shifts(): void
    {
        $morning = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '10:00']);
        $noon = Shift::factory()->create(['start_time' => '10:00', 'end_time' => '14:00']);
        $this->openCell($this->workcenter, $this->early, self::MONDAY);
        $this->openCell($this->workcenter, $this->late, self::MONDAY);
        $this->openCell($this->workcenter, $morning, '2026-09-08');
        $this->openCell($this->workcenter, $noon, '2026-09-08');
        $first = $this->worker();
        $second = $this->worker();
        foreach ([$first, $second] as $employee) {
            $this->makeAvailable($employee, $morning, '2026-09-08');
            $this->makeAvailable($employee, $noon, '2026-09-08');
        }
        $this->hold($first, $this->early, self::PREVIOUS_MONDAY);
        $this->hold($first, $morning, '2026-09-01');

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->repeatsAlone($first, $this->early, $this->late, self::MONDAY), 'control: the first employee repeats Early');
        $this->assertTrue($this->repeatsAlone($first, $morning, $noon, '2026-09-08'), 'control: the first employee repeats Morning');

        $this->clearCycle();
        $this->pair();
        $this->pair(10, $morning, $noon);
        $this->generator()->generate($this->makeRun());

        $this->assertFalse($this->repeatsAlone($first, $this->early, $this->late, self::MONDAY));
        $this->assertFalse($this->repeatsAlone($first, $morning, $noon, '2026-09-08'));
        $this->assertSame(2, ShiftAssignment::query()->whereDate('date', self::MONDAY)->count(), 'coverage stays complete');
    }

    // ── No preference ───────────────────────────────────────────────────

    public function test_there_is_no_preference_when_the_earlier_day_holds_both_members(): void
    {
        $this->openCell($this->workcenter, $this->early, self::MONDAY);
        $this->openCell($this->workcenter, $this->late, self::MONDAY);
        $first = $this->worker();
        $this->worker();
        $this->hold($first, $this->early, self::PREVIOUS_MONDAY);
        $this->hold($first, $this->late, self::PREVIOUS_MONDAY);
        $this->pair();

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($first, $this->early, self::MONDAY), 'the first employee keeps the shift the planner gave without the rule');
    }

    public function test_there_is_no_preference_when_the_earlier_day_holds_neither_member(): void
    {
        $this->openCell($this->workcenter, $this->early, self::MONDAY);
        $this->openCell($this->workcenter, $this->late, self::MONDAY);
        // The first employee starts heavier, so the planner gives them Late. Nothing
        // was worked a week earlier, so the rule has no preference against that.
        $first = $this->worker();
        $this->worker();
        $this->weigh($first);
        $this->pair();

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($first, $this->late, self::MONDAY));
    }

    public function test_holding_both_members_that_day_satisfies_the_rule(): void
    {
        $this->openCell($this->workcenter, $this->early, self::MONDAY);
        $this->openCell($this->workcenter, $this->late, self::MONDAY);
        $first = $this->worker();
        // The second employee can only take Early, and only on a not-preferred cell.
        $second = $this->employee();
        $this->makeAvailable($second, $this->early, self::MONDAY, 'not_preferred');
        $this->hold($first, $this->early, self::PREVIOUS_MONDAY);
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);
        $this->pair(8);

        $this->generator()->generate($this->makeRun());

        // The first employee repeats Early but also works Late, so the rule is met
        // and the not-preferred cost of 5 is avoided.
        $this->assertTrue($this->holds($first, $this->early, self::MONDAY));
        $this->assertTrue($this->holds($first, $this->late, self::MONDAY));
        $this->assertFalse($this->holds($second, $this->early, self::MONDAY));
    }

    // ── Severity ────────────────────────────────────────────────────────

    public function test_a_repeat_costs_the_severity_and_loses_to_a_higher_cost(): void
    {
        $this->openCell($this->workcenter, $this->early, self::MONDAY);
        $first = $this->worker(); // worked Early a week earlier, so Early again is a repeat
        // Free of the repeat, but on a not-preferred cell that costs 5.
        $second = $this->employee();
        $this->makeAvailable($second, $this->early, self::MONDAY, 'not_preferred');
        $this->hold($first, $this->early, self::PREVIOUS_MONDAY);
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);
        $pair = $this->pair(3);

        // Repeating costs 3, below 5: the first employee keeps the shift.
        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->holds($first, $this->early, self::MONDAY));

        // Repeating costs 8, above 5: the second employee takes it.
        $this->clearCycle();
        $pair->update(['severity' => 8]);
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($second, $this->early, self::MONDAY));
        $this->assertFalse($this->holds($first, $this->early, self::MONDAY));
    }

    public function test_the_rule_is_broken_when_that_is_the_only_way_to_fill_the_spot(): void
    {
        $this->openCell($this->workcenter, $this->early, self::MONDAY);
        $only = $this->worker();
        $this->hold($only, $this->early, self::PREVIOUS_MONDAY);
        $this->pair();

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertTrue($this->holds($only, $this->early, self::MONDAY));
        $this->assertSame([], $run->refresh()->unfulfilled);
    }
}
