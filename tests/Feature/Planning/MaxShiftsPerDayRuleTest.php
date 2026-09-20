<?php

namespace Tests\Feature\Planning;

use App\Models\Employee;
use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftDateOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaxShiftsPerDayRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    public function test_unfulfilled_reason_is_hard_cap_reached_once_the_only_candidate_is_capped_elsewhere(): void
    {
        [, $shiftA] = $this->workcenterWithOverride('2026-09-08');
        $workcenterB = Workcenter::factory()->create();
        $shiftB = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $workcenterB->shifts()->attach($shiftB);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenterB->id, 'shift_id' => $shiftB->id, 'date' => '2026-09-08', 'spots' => 1,
        ]);
        // The only employee at all — eligible for both cells.
        $employee = $this->eligibleEmployee($shiftA, '2026-09-08');
        $this->makeAvailable($employee, $shiftB, '2026-09-08');
        PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'hard', 'config' => ['value' => 1]]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertCount(1, ShiftAssignment::all());
        $unfulfilled = $run->refresh()->unfulfilled;
        $this->assertCount(1, $unfulfilled);
        $this->assertSame('hard_cap_reached', $unfulfilled[0]['reason']);
    }

    public function test_soft_max_shifts_per_day_prefers_the_candidate_under_the_cap(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shiftX1 = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '08:00']);
        $shiftX2 = Shift::factory()->create(['start_time' => '08:00', 'end_time' => '10:00']);
        $shiftY = Shift::factory()->create(['start_time' => '10:00', 'end_time' => '12:00']);
        $shiftZ = Shift::factory()->create(['start_time' => '12:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach([$shiftX1->id, $shiftX2->id, $shiftZ->id]);

        $atCap = $this->employee(); // already 2 shifts that day
        $underCap = $this->employee(); // already 1 shift that day
        ShiftAssignment::factory()->create([
            'employee_id' => $atCap->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shiftX1->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $atCap->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shiftX2->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $underCap->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shiftZ->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        $this->makeAvailable($atCap, $shiftY, '2026-09-08');
        $this->makeAvailable($underCap, $shiftY, '2026-09-08');
        $this->openCell($workcenter, $shiftY, '2026-09-08');
        WorkcenterShiftDateOverride::query()->where('shift_id', $shiftY->id)->update(['spots' => 1]);

        PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'soft', 'severity' => 10, 'config' => ['value' => 2]]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', [
            'employee_id' => $underCap->id, 'shift_id' => $shiftY->id, 'date' => '2026-09-08',
        ]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $atCap->id, 'shift_id' => $shiftY->id]);
    }
}
