<?php

namespace Tests\Feature\Planning;

use App\Models\Competence;
use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotPreferredShiftRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    // ── Soft ────────────────────────────────────────────────────────────

    public function test_a_soft_rule_prefers_an_available_candidate_over_a_not_preferred_one(): void
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
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $available->id, 'date' => '2026-09-08']);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $notPreferred->id]);
    }

    public function test_a_soft_rule_is_broken_when_that_is_the_only_way_to_fill_the_spot(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $notPreferred = $this->employee();
        $this->makeAvailable($notPreferred, $shift, '2026-09-08', 'not_preferred');
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 10]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $notPreferred->id]);
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_a_soft_rule_ignores_marks_on_other_weekdays_and_shifts(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08'); // Tuesday
        $otherShift = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        // Created first, so it wins a tie. Its not-preferred marks are on Wednesday
        // of the same shift and on Tuesday of another shift, neither on this cell.
        $marked = $this->employee();
        $this->makeAvailable($marked, $shift, '2026-09-08');
        $this->makeAvailable($marked, $shift, '2026-09-09', 'not_preferred');
        $this->makeAvailable($marked, $otherShift, '2026-09-08', 'not_preferred');
        $plain = $this->employee();
        $this->makeAvailable($plain, $shift, '2026-09-08');
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);

        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $marked->id, 'date' => '2026-09-08']);
    }

    public function test_a_soft_rule_costs_its_severity_and_loses_to_a_higher_cost(): void
    {
        [$workcenter, $shift] = $this->workcenterWithOverride('2026-09-08');
        $competence = Competence::factory()->create();
        // Holds the competence, but on a not-preferred cell.
        $notPreferred = $this->employee();
        $notPreferred->competences()->attach($competence->id);
        $this->makeAvailable($notPreferred, $shift, '2026-09-08', 'not_preferred');
        // Available, but missing the competence, which costs 5.
        $missing = $this->employee();
        $this->makeAvailable($missing, $shift, '2026-09-08');
        PlanningRule::create([
            'type' => 'competence_required', 'mode' => 'soft', 'severity' => 5,
            'config' => ['workcenter_id' => $workcenter->id, 'competence_id' => $competence->id],
        ]);
        $rule = PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 3]);

        // The not-preferred cell costs 3, below 5: the candidate on it wins.
        $this->generator()->generate($this->makeRun());
        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $notPreferred->id]);

        // It costs 8, above 5: the available candidate wins.
        ShiftAssignment::query()->delete();
        $rule->update(['severity' => 8]);
        $this->generator()->generate($this->makeRun());

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $missing->id]);
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
