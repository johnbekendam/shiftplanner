<?php

namespace Tests\Feature\Planning;

use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaxHoursPerWeekRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    public function test_soft_max_hours_per_week_prefers_the_candidate_under_the_cap(): void
    {
        $workcenter = Workcenter::factory()->create();
        $existingShift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']); // 8h
        $openShift = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']); // 8h
        $workcenter->shifts()->attach($existingShift->id);

        // weekly_hours 4 -> cap 8h over the cycle. atCap already holds exactly 8h.
        $atCap = $this->employee(['weekly_hours' => 4]);
        $underCap = $this->employee(['weekly_hours' => 4]);
        ShiftAssignment::factory()->create([
            'employee_id' => $atCap->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $existingShift->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        $this->makeAvailable($atCap, $openShift, '2026-09-08');
        $this->makeAvailable($underCap, $openShift, '2026-09-08');
        $this->openCell($workcenter, $openShift, '2026-09-08');

        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'soft', 'severity' => 10]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', [
            'employee_id' => $underCap->id, 'shift_id' => $openShift->id, 'date' => '2026-09-08',
        ]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $atCap->id, 'shift_id' => $openShift->id]);
    }
}
