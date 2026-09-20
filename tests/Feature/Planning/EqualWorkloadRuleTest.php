<?php

namespace Tests\Feature\Planning;

use App\Models\Employee;
use App\Models\PlanningRule;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Equal workload minimizes the highest absolute hour total after coverage.
 * Greedy construction already spreads open spots by hours, so the scenarios
 * start from assignments that already exist. Nothing is open, and only the
 * optimizer can rebalance them. Every shift lasts 8h.
 */
class EqualWorkloadRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    private const MONDAY = '2026-09-07';

    private const TUESDAY = '2026-09-08';

    private const WEDNESDAY = '2026-09-09';

    private Workcenter $workcenter;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workcenter = Workcenter::factory()->create();
        $this->shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->workcenter->shifts()->attach($this->shift);
    }

    /** Opens the cell and makes each employee available for it. */
    private function cell(string $date, Employee ...$employees): void
    {
        $this->openDay($this->workcenter, $this->shift, $date);
        foreach ($employees as $employee) {
            $this->makeAvailable($employee, $this->shift, $date);
        }
    }

    private function hold(Employee $employee, string $date, bool $fixed = false, ?Workcenter $workcenter = null): ShiftAssignment
    {
        return ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => ($workcenter ?? $this->workcenter)->id,
            'shift_id' => $this->shift->id, 'date' => $date, 'fixed' => $fixed,
        ]);
    }

    private function holdings(Employee $employee): int
    {
        return ShiftAssignment::query()->where('employee_id', $employee->id)->count();
    }

    // ── Rebalancing ─────────────────────────────────────────────────────

    public function test_it_rebalances_a_fully_staffed_cycle(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $this->cell(self::TUESDAY, $a, $b);
        $this->cell(self::WEDNESDAY, $a, $b);
        $this->hold($a, self::TUESDAY);
        $this->hold($a, self::WEDNESDAY);

        $this->generator()->generate($this->makeRun());
        $this->assertSame(2, $this->holdings($a), 'control: without the rule nothing moves');

        PlanningRule::create(['type' => 'equal_workload']);
        $this->generator()->generate($this->makeRun());

        $this->assertSame(1, $this->holdings($a));
        $this->assertSame(1, $this->holdings($b));
    }

    public function test_it_compares_absolute_hours_not_a_percentage_of_weekly_hours(): void
    {
        // A offers 20 weekly hours, B offers 40. B holds 16h, which is 40% of the
        // offer. Moving one shift to A gives A 8h (40%) and B 8h (20%). The highest
        // percentage stays at 40%, so only the absolute hours (16h down to 8h) show
        // a gain.
        $a = $this->employee(['weekly_hours' => 20]);
        $b = $this->employee(['weekly_hours' => 40]);
        $this->cell(self::TUESDAY, $a, $b);
        $this->cell(self::WEDNESDAY, $a, $b);
        $this->hold($b, self::TUESDAY);
        $this->hold($b, self::WEDNESDAY);

        $this->generator()->generate($this->makeRun());
        $this->assertSame(2, $this->holdings($b), 'control: without the rule nothing moves');

        PlanningRule::create(['type' => 'equal_workload']);
        $this->generator()->generate($this->makeRun());

        $this->assertSame(1, $this->holdings($a));
        $this->assertSame(1, $this->holdings($b));
    }

    public function test_it_does_not_leave_a_spot_open_to_lower_the_highest_total(): void
    {
        // Leaving a spot open would lower the highest total from 16h to 8h.
        $only = $this->employee();
        $this->cell(self::TUESDAY, $only);
        $this->cell(self::WEDNESDAY, $only);
        PlanningRule::create(['type' => 'equal_workload']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(2, $this->holdings($only));
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    // ── Locked assignments ──────────────────────────────────────────────

    public function test_the_hours_of_a_fixed_assignment_count_toward_the_total(): void
    {
        // A holds 16h: a fixed shift on Tuesday and a movable one on Wednesday.
        // C holds nothing. Moving the Wednesday shift to C gives 8h and 8h, but
        // only if the fixed 8h count. Without them A would go from 8h to 0h and C
        // from 0h to 8h, which is no gain.
        $a = $this->employee();
        $c = $this->employee();
        $this->cell(self::TUESDAY, $a, $c);
        $this->cell(self::WEDNESDAY, $a, $c);
        $this->hold($a, self::TUESDAY, fixed: true);
        $this->hold($a, self::WEDNESDAY);

        $this->generator()->generate($this->makeRun());
        $this->assertSame(2, $this->holdings($a), 'control: without the rule nothing moves');

        PlanningRule::create(['type' => 'equal_workload']);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $c->id, 'date' => self::WEDNESDAY]);
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $a->id, 'date' => self::TUESDAY, 'fixed' => true]);
        $this->assertSame(1, $this->holdings($a));
    }

    public function test_the_hours_of_a_published_assignment_count_toward_the_total(): void
    {
        // The same case, with the locked shift in a published workcenter-week and
        // the movable one in another workcenter.
        $published = $this->workcenter;
        $open = Workcenter::factory()->create();
        $open->shifts()->attach($this->shift);
        $a = $this->employee();
        $c = $this->employee();
        $this->cell(self::TUESDAY, $a, $c);
        $this->openDay($open, $this->shift, self::WEDNESDAY);
        $this->makeAvailable($a, $this->shift, self::WEDNESDAY);
        $this->makeAvailable($c, $this->shift, self::WEDNESDAY);
        $this->hold($a, self::TUESDAY);
        $this->hold($a, self::WEDNESDAY, workcenter: $open);
        PublishedWeek::query()->create(['week_start' => self::MONDAY, 'workcenter_id' => $published->id]);

        $this->generator()->generate($this->makeRun());
        $this->assertSame(2, $this->holdings($a), 'control: without the rule nothing moves');

        PlanningRule::create(['type' => 'equal_workload']);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $c->id, 'date' => self::WEDNESDAY]);
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $a->id, 'date' => self::TUESDAY]);
    }

    public function test_a_fixed_assignment_stays_even_when_moving_it_would_improve_fairness(): void
    {
        $a = $this->employee();
        $c = $this->employee();
        $this->cell(self::TUESDAY, $a, $c);
        $this->cell(self::WEDNESDAY, $a, $c);
        PlanningRule::create(['type' => 'equal_workload']);

        // Control: movable shifts do move, so the optimizer does want this change.
        $this->hold($a, self::TUESDAY);
        $this->hold($a, self::WEDNESDAY);
        $this->generator()->generate($this->makeRun());
        $this->assertSame(1, $this->holdings($c));

        ShiftAssignment::query()->delete();
        $this->hold($a, self::TUESDAY, fixed: true);
        $this->hold($a, self::WEDNESDAY, fixed: true);
        $this->generator()->generate($this->makeRun());

        $this->assertSame(2, $this->holdings($a));
        $this->assertSame(0, $this->holdings($c));
    }

    public function test_a_published_assignment_stays_even_when_moving_it_would_improve_fairness(): void
    {
        $a = $this->employee();
        $c = $this->employee();
        $this->cell(self::TUESDAY, $a, $c);
        $this->cell(self::WEDNESDAY, $a, $c);
        $this->hold($a, self::TUESDAY);
        $this->hold($a, self::WEDNESDAY);
        PlanningRule::create(['type' => 'equal_workload']);

        // Control: unpublished, the optimizer moves a shift to C.
        $this->generator()->generate($this->makeRun());
        $this->assertSame(1, $this->holdings($c));

        ShiftAssignment::query()->delete();
        $this->hold($a, self::TUESDAY);
        $this->hold($a, self::WEDNESDAY);
        PublishedWeek::query()->create(['week_start' => self::MONDAY, 'workcenter_id' => $this->workcenter->id]);
        $this->generator()->generate($this->makeRun());

        $this->assertSame(2, $this->holdings($a));
        $this->assertSame(0, $this->holdings($c));
    }
}
