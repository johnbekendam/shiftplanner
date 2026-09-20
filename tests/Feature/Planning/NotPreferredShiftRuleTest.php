<?php

namespace Tests\Feature\Planning;

use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotPreferredShiftRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    public function test_not_preferred_shift_is_avoided_in_favor_of_an_available_candidate(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->openCell($workcenter, $shift, '2026-09-08');

        // Created first (lower id), so construction's stable tie-break would pick it
        // if soft preference weren't considered at all.
        $notPreferred = $this->employee();
        $this->makeAvailable($notPreferred, $shift, '2026-09-08', 'not_preferred');
        $available = $this->employee();
        $this->makeAvailable($available, $shift, '2026-09-08', 'available');

        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $available->id, 'date' => '2026-09-08']);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $notPreferred->id]);
    }

    // Hard mode: a not-preferred cell is as closed as an unavailable one.

    public function test_a_hard_rule_excludes_a_not_preferred_cell(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = $this->employee();
        $this->makeAvailable($employee, $shift, '2026-09-08', 'not_preferred');
        $rule = PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'hard']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame('no_eligible_employee', $run->refresh()->unfulfilled[0]['reason']);

        // Control: without the rule the same employee takes the cell.
        $rule->delete();
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $employee->id, 'date' => '2026-09-08']);
    }

    public function test_a_hard_rule_picks_the_available_candidate_over_a_not_preferred_one(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        // Created first, so the planner picks it when no rule exists.
        $notPreferred = $this->employee();
        $this->makeAvailable($notPreferred, $shift, '2026-09-08', 'not_preferred');
        $available = $this->employee();
        $this->makeAvailable($available, $shift, '2026-09-08');

        $this->generator()->generate($this->makeRun());
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $notPreferred->id]);

        ShiftAssignment::query()->delete();
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'hard']);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $available->id]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $notPreferred->id]);
    }

    public function test_a_hard_rule_only_closes_the_weekday_and_shift_the_employee_marked(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08'); // Tuesday
        $employee = $this->employee();
        $this->makeAvailable($employee, $shift, '2026-09-08');
        $this->makeAvailable($employee, $shift, '2026-09-09', 'not_preferred'); // Wednesday
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'hard']);

        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $employee->id, 'date' => '2026-09-08']);
    }
}
