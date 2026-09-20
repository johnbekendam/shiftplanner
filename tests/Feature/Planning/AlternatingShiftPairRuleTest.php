<?php

namespace Tests\Feature\Planning;

use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlternatingShiftPairRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    public function test_alternating_shift_pair_is_honored_against_the_previous_week(): void
    {
        $workcenter = Workcenter::factory()->create();
        $early = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $late = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $this->openCell($workcenter, $early, '2026-09-07');
        $this->openCell($workcenter, $late, '2026-09-07');

        $e1 = $this->employee();
        $e2 = $this->employee();
        foreach ([$early, $late] as $shift) {
            $this->makeAvailable($e1, $shift, '2026-09-07');
            $this->makeAvailable($e2, $shift, '2026-09-07');
        }
        // The Monday one week before the cycle: e1 worked Early (exactly one pair
        // member), so the opposite (Late) is preferred for e1 on the cycle's Monday.
        ShiftAssignment::factory()->create([
            'employee_id' => $e1->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $early->id, 'date' => '2026-08-31', 'fixed' => false,
        ]);

        PlanningRule::create([
            'type' => 'alternating_shift_pair', 'mode' => 'soft', 'severity' => 10,
            'config' => ['first_shift_id' => $early->id, 'second_shift_id' => $late->id],
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        // e1 must not hold Early without also holding Late.
        $e1HasEarly = ShiftAssignment::where(['employee_id' => $e1->id, 'shift_id' => $early->id, 'date' => '2026-09-07'])->exists();
        $e1HasLate = ShiftAssignment::where(['employee_id' => $e1->id, 'shift_id' => $late->id, 'date' => '2026-09-07'])->exists();
        $this->assertFalse($e1HasEarly && ! $e1HasLate, 'e1 repeated Early without the preferred opposite (Late).');
        // Both spots are still covered either way.
        $this->assertSame(2, ShiftAssignment::whereDate('date', '2026-09-07')->count());
    }
}
