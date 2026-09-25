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
 * The pair rule is a hard weekly exclusion: an employee never holds both
 * pair shifts in the same Monday–Sunday week, across all workcenters.
 * Every scenario first runs without the rule (control) to show the planner
 * would combine the pair. The pair is Early and Late.
 */
class AlternatingShiftPairRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    private const MONDAY = '2026-09-07';

    private const WEDNESDAY = '2026-09-09';

    private const NEXT_WEDNESDAY = '2026-09-16';

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

    private function pair(): PlanningRule
    {
        return PlanningRule::create([
            'type' => 'alternating_shift_pair', 'mode' => 'hard', 'severity' => null,
            'config' => ['first_shift_id' => $this->early->id, 'second_shift_id' => $this->late->id],
        ]);
    }

    private function hold(Employee $employee, Shift $shift, string $date, ?Workcenter $workcenter = null): void
    {
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => ($workcenter ?? $this->workcenter)->id,
            'shift_id' => $shift->id, 'date' => $date, 'fixed' => true,
        ]);
    }

    private function holds(Employee $employee, Shift $shift, string $date): bool
    {
        return ShiftAssignment::query()
            ->where(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'date' => $date])
            ->exists();
    }

    /** Two fixed 8h shifts on Thursday and Friday, so the employee starts heavier than one holding a single shift. */
    private function weigh(Employee $employee): void
    {
        $night = Shift::factory()->create(['start_time' => '00:00', 'end_time' => '08:00']);
        $this->hold($employee, $night, '2026-09-10');
        $this->hold($employee, $night, '2026-09-11');
    }

    private function clearCycle(): void
    {
        ShiftAssignment::query()->where('fixed', false)->delete();
    }

    /**
     * The first employee holds $held on Monday and is the lighter candidate
     * for $open on Wednesday. The second employee is heavier.
     *
     * @return array{Employee, Employee}
     */
    private function sameWeekScenario(Shift $held, Shift $open): array
    {
        $this->openCell($this->workcenter, $open, self::WEDNESDAY);
        $first = $this->eligibleEmployee($open, self::WEDNESDAY);
        $second = $this->eligibleEmployee($open, self::WEDNESDAY);
        $this->hold($first, $held, self::MONDAY);
        $this->weigh($second);

        return [$first, $second];
    }

    public function test_an_employee_holding_early_is_not_given_late_in_the_same_week(): void
    {
        [$first, $second] = $this->sameWeekScenario($this->early, $this->late);

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->holds($first, $this->late, self::WEDNESDAY), 'control: without the rule the lighter employee combines the pair');

        $this->clearCycle();
        $this->pair();
        $this->generator()->generate($this->makeRun());

        $this->assertFalse($this->holds($first, $this->late, self::WEDNESDAY));
        $this->assertTrue($this->holds($second, $this->late, self::WEDNESDAY));
    }

    public function test_the_rule_works_from_either_member_of_the_pair(): void
    {
        [$first, $second] = $this->sameWeekScenario($this->late, $this->early);

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->holds($first, $this->early, self::WEDNESDAY), 'control: without the rule the lighter employee combines the pair');

        $this->clearCycle();
        $this->pair();
        $this->generator()->generate($this->makeRun());

        $this->assertFalse($this->holds($first, $this->early, self::WEDNESDAY));
        $this->assertTrue($this->holds($second, $this->early, self::WEDNESDAY));
    }

    public function test_the_planner_does_not_combine_the_pair_among_its_own_assignments(): void
    {
        $this->openCell($this->workcenter, $this->early, self::MONDAY);
        $this->openCell($this->workcenter, $this->late, self::WEDNESDAY);
        $only = $this->eligibleEmployee($this->early, self::MONDAY);
        $this->makeAvailable($only, $this->late, self::WEDNESDAY);

        $this->generator()->generate($this->makeRun());
        $this->assertSame(2, ShiftAssignment::count(), 'control: without the rule the only employee takes both shifts');

        $this->clearCycle();
        $this->pair();
        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(1, ShiftAssignment::count());
        $this->assertCount(1, $run->refresh()->unfulfilled);
    }

    public function test_the_rule_counts_shifts_held_in_other_workcenters(): void
    {
        $other = Workcenter::factory()->create();
        $this->openCell($this->workcenter, $this->late, self::WEDNESDAY);
        $employee = $this->eligibleEmployee($this->late, self::WEDNESDAY);
        $this->hold($employee, $this->early, self::MONDAY, $other);
        $this->pair();

        $this->generator()->generate($this->makeRun());

        $this->assertFalse($this->holds($employee, $this->late, self::WEDNESDAY));
    }

    public function test_the_other_week_is_not_affected(): void
    {
        $this->openCell($this->workcenter, $this->late, self::NEXT_WEDNESDAY);
        $employee = $this->eligibleEmployee($this->late, self::NEXT_WEDNESDAY);
        $this->hold($employee, $this->early, self::MONDAY);
        $this->pair();

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($employee, $this->late, self::NEXT_WEDNESDAY));
    }

    public function test_a_shift_outside_the_pair_is_not_affected(): void
    {
        $mid = Shift::factory()->create(['start_time' => '10:00', 'end_time' => '18:00']);
        $this->openCell($this->workcenter, $mid, self::WEDNESDAY);
        $employee = $this->eligibleEmployee($mid, self::WEDNESDAY);
        $this->hold($employee, $this->early, self::MONDAY);
        $this->pair();

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($employee, $mid, self::WEDNESDAY));
    }

    public function test_the_same_pair_shift_can_repeat_within_a_week(): void
    {
        $this->openCell($this->workcenter, $this->early, self::WEDNESDAY);
        $employee = $this->eligibleEmployee($this->early, self::WEDNESDAY);
        $this->hold($employee, $this->early, self::MONDAY);
        $this->pair();

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->holds($employee, $this->early, self::WEDNESDAY));
    }
}
