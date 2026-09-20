<?php

namespace Tests\Feature\Planning;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A business-line preference belongs to one workcenter. The scenarios create
 * the candidate from the wrong line first, so the planner picks it when no
 * rule exists, and the run repeats with the rule.
 */
class BusinessLinePreferenceRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    private Workcenter $workcenter;

    private Shift $shift;

    private BusinessLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workcenter = Workcenter::factory()->create();
        $this->shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->openCell($this->workcenter, $this->shift, '2026-09-08');
        $this->line = BusinessLine::factory()->create();
    }

    private function candidate(?BusinessLine $line, string $level = 'available'): Employee
    {
        $employee = $this->employee(['business_line_id' => $line?->id]);
        $this->makeAvailable($employee, $this->shift, '2026-09-08', $level);

        return $employee;
    }

    private function prefer(string $mode, ?int $severity = null, ?Workcenter $workcenter = null): PlanningRule
    {
        return PlanningRule::create([
            'type' => 'business_line_preference', 'mode' => $mode, 'severity' => $severity,
            'config' => ['workcenter_id' => ($workcenter ?? $this->workcenter)->id, 'business_line_id' => $this->line->id],
        ]);
    }

    private function assigned(Employee $employee): bool
    {
        return ShiftAssignment::query()->where('employee_id', $employee->id)->exists();
    }

    // ── Hard ────────────────────────────────────────────────────────────

    public function test_a_hard_preference_excludes_a_candidate_from_another_line(): void
    {
        $other = $this->candidate(BusinessLine::factory()->create());
        $matching = $this->candidate($this->line);

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->assigned($other), 'control: without the rule the first candidate is picked');

        ShiftAssignment::query()->delete();
        $this->prefer('hard');
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($matching));
        $this->assertFalse($this->assigned($other));
    }

    public function test_a_hard_preference_excludes_a_candidate_without_a_business_line(): void
    {
        $none = $this->candidate(null);
        $matching = $this->candidate($this->line);

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->assigned($none), 'control: without the rule the first candidate is picked');

        ShiftAssignment::query()->delete();
        $this->prefer('hard');
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($matching));
        $this->assertFalse($this->assigned($none));
    }

    public function test_a_hard_preference_leaves_the_spot_open_when_nobody_matches(): void
    {
        $other = $this->candidate(BusinessLine::factory()->create());
        $rule = $this->prefer('hard');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame('no_eligible_employee', $run->refresh()->unfulfilled[0]['reason']);

        $rule->delete();
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($other));
    }

    public function test_a_hard_preference_does_not_apply_to_another_workcenter(): void
    {
        $other = $this->candidate(BusinessLine::factory()->create());
        $this->candidate($this->line);
        $this->prefer('hard', workcenter: Workcenter::factory()->create());

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($other));
    }

    // ── Soft ────────────────────────────────────────────────────────────

    public function test_a_soft_preference_prefers_a_candidate_from_the_line(): void
    {
        $other = $this->candidate(BusinessLine::factory()->create());
        $matching = $this->candidate($this->line);

        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->assigned($other), 'control: without the rule the first candidate is picked');

        ShiftAssignment::query()->delete();
        $this->prefer('soft', 10);
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($matching));
        $this->assertFalse($this->assigned($other));
    }

    public function test_a_soft_preference_counts_a_candidate_without_a_line_as_not_matching(): void
    {
        $none = $this->candidate(null);
        $matching = $this->candidate($this->line);
        $this->prefer('soft', 10);

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($matching));
        $this->assertFalse($this->assigned($none));
    }

    public function test_a_soft_preference_is_broken_when_that_is_the_only_way_to_fill_the_spot(): void
    {
        $other = $this->candidate(BusinessLine::factory()->create());
        $this->prefer('soft', 10);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertTrue($this->assigned($other));
        $this->assertSame([], $run->refresh()->unfulfilled);
    }

    public function test_a_soft_preference_does_not_apply_to_another_workcenter(): void
    {
        $other = $this->candidate(BusinessLine::factory()->create());
        $this->candidate($this->line);
        $this->prefer('soft', 10, Workcenter::factory()->create());

        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($other));
    }

    public function test_a_soft_preference_costs_its_severity_and_loses_to_a_higher_cost(): void
    {
        $other = $this->candidate(BusinessLine::factory()->create());
        // From the line, but on a not-preferred cell that costs 5.
        $matching = $this->candidate($this->line, 'not_preferred');
        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);
        $preference = $this->prefer('soft', 3);

        // Leaving the line costs 3, below 5: the candidate from another line wins.
        $this->generator()->generate($this->makeRun());
        $this->assertTrue($this->assigned($other));

        // Leaving the line costs 8, above 5: the candidate from the line wins.
        ShiftAssignment::query()->delete();
        $preference->update(['severity' => 8]);
        $this->generator()->generate($this->makeRun());

        $this->assertTrue($this->assigned($matching));
        $this->assertFalse($this->assigned($other));
    }
}
