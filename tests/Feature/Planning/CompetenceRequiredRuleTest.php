<?php

namespace Tests\Feature\Planning;

use App\Models\Competence;
use App\Models\Employee;
use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A competence requirement belongs to one workcenter. The scenarios create the
 * candidate without the competence first, so the planner picks it when no rule
 * exists, and the run repeats with the rule.
 */
class CompetenceRequiredRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    private Workcenter $workcenter;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workcenter = Workcenter::factory()->create();
        $this->shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->openCell($this->workcenter, $this->shift, '2026-09-08');
    }

    private function candidate(array $competences = [], string $level = 'available'): Employee
    {
        $employee = $this->employee();
        $employee->competences()->attach(array_map(fn (Competence $c) => $c->id, $competences));
        $this->makeAvailable($employee, $this->shift, '2026-09-08', $level);

        return $employee;
    }

    private function requirement(Competence $competence, string $mode, ?int $severity = null, ?Workcenter $workcenter = null): PlanningRule
    {
        return PlanningRule::create([
            'type' => 'competence_required', 'mode' => $mode, 'severity' => $severity,
            'config' => ['workcenter_id' => ($workcenter ?? $this->workcenter)->id, 'competence_id' => $competence->id],
        ]);
    }

    private function assigned(Employee $employee): bool
    {
        return ShiftAssignment::query()->where('employee_id', $employee->id)->exists();
    }

    // ── Hard ────────────────────────────────────────────────────────────

    public function test_a_hard_requirement_excludes_a_candidate_without_the_competence(): void
    {
        $competence = Competence::factory()->create();
        $without = $this->candidate();
        $with = $this->candidate([$competence]);

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->assigned($without), 'control: without the rule the first candidate is picked');

        ShiftAssignment::query()->delete();
        $this->requirement($competence, 'hard');
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($with));
        $this->assertFalse($this->assigned($without));
    }

    public function test_a_hard_requirement_leaves_the_spot_open_when_nobody_holds_the_competence(): void
    {
        $competence = Competence::factory()->create();
        $without = $this->candidate();
        $rule = $this->requirement($competence, 'hard');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame('no_eligible_employee', $run->refresh()->unfulfilled[0]['reason']);

        $rule->delete();
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($without));
    }

    public function test_two_hard_requirements_on_one_workcenter_both_have_to_be_met(): void
    {
        $first = Competence::factory()->create();
        $second = Competence::factory()->create();
        // Holds the first requirement only. Created first, so it wins without the rules.
        $partial = $this->candidate([$first]);
        $both = $this->candidate([$first, $second]);

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->assigned($partial), 'control: without the rules the first candidate is picked');

        ShiftAssignment::query()->delete();
        $this->requirement($first, 'hard');
        $this->requirement($second, 'hard');
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($both));
        $this->assertFalse($this->assigned($partial));
    }

    public function test_a_hard_requirement_does_not_apply_to_another_workcenter(): void
    {
        $competence = Competence::factory()->create();
        $other = Workcenter::factory()->create();
        $without = $this->candidate();
        $this->candidate([$competence]);
        $this->requirement($competence, 'hard', workcenter: $other);

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($without));
    }

    // ── Soft ────────────────────────────────────────────────────────────

    public function test_a_soft_requirement_prefers_a_candidate_with_the_competence(): void
    {
        $competence = Competence::factory()->create();
        $without = $this->candidate();
        $with = $this->candidate([$competence]);

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->assigned($without), 'control: without the rule the first candidate is picked');

        ShiftAssignment::query()->delete();
        $this->requirement($competence, 'soft', 10);
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($with));
        $this->assertFalse($this->assigned($without));
    }

    public function test_a_soft_requirement_is_broken_when_that_is_the_only_way_to_fill_the_spot(): void
    {
        $competence = Competence::factory()->create();
        $without = $this->candidate();
        $this->requirement($competence, 'soft', 10);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertTrue($this->assigned($without));
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_a_soft_requirement_does_not_apply_to_another_workcenter(): void
    {
        $competence = Competence::factory()->create();
        $other = Workcenter::factory()->create();
        $without = $this->candidate();
        $this->candidate([$competence]);
        $this->requirement($competence, 'soft', 10, workcenter: $other);

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($without));
    }

    public function test_a_soft_requirement_costs_its_severity_and_loses_to_a_higher_cost(): void
    {
        $competence = Competence::factory()->create();
        $without = $this->candidate();
        // Holds the competence, but on a not-preferred cell that costs 5.
        $with = $this->candidate([$competence], 'not_preferred');
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);
        $requirement = $this->requirement($competence, 'soft', 3);

        // Missing the competence costs 3, below 5: the candidate without it wins.
        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->assigned($without));

        // Missing it costs 8, above 5: the candidate with it wins.
        ShiftAssignment::query()->delete();
        $requirement->update(['severity' => 8]);
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($with));
        $this->assertFalse($this->assigned($without));
    }

    public function test_every_missing_soft_competence_adds_its_severity(): void
    {
        $first = Competence::factory()->create();
        $second = Competence::factory()->create();
        $neither = $this->candidate();
        $both = $this->candidate([$first, $second], 'not_preferred'); // costs 5
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);
        $firstRule = $this->requirement($first, 'soft', 2);
        $secondRule = $this->requirement($second, 'soft', 2);

        // Missing both costs 2 + 2 = 4, below 5: the candidate with neither wins.
        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->assigned($neither));

        // Missing both costs 3 + 3 = 6, above 5. Counting only one requirement (3) would keep the first winner.
        ShiftAssignment::query()->delete();
        $firstRule->update(['severity' => 3]);
        $secondRule->update(['severity' => 3]);
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($both));
        $this->assertFalse($this->assigned($neither));
    }
}
