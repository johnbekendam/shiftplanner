<?php

namespace Tests\Feature\Planning;

use App\Models\PlanningRule;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftDateOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EqualWorkloadRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    public function test_equal_workload_rebalances_a_fully_staffed_cycle_via_substitute(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']); // 8h
        $workcenter->shifts()->attach($shift);

        $a = $this->employee();
        $b = $this->employee();
        foreach (['2026-09-08', '2026-09-09'] as $date) {
            $this->makeAvailable($a, $shift, $date);
            $this->makeAvailable($b, $shift, $date);
            WorkcenterShiftDateOverride::query()->create([
                'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date, 'spots' => 1,
            ]);
        }
        // Both spots already fully staffed by A alone — nothing is "open" for construction.
        ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-09', 'fixed' => false,
        ]);
        PlanningRule::create(['type' => 'equal_workload']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(1, ShiftAssignment::where('employee_id', $a->id)->count());
        $this->assertSame(1, ShiftAssignment::where('employee_id', $b->id)->count());
    }

    public function test_a_fixed_assignment_is_never_substituted_away_even_to_improve_fairness(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach($shift);

        $a = $this->employee();
        $b = $this->employee();
        foreach (['2026-09-08', '2026-09-09'] as $date) {
            $this->makeAvailable($a, $shift, $date);
            $this->makeAvailable($b, $shift, $date);
            WorkcenterShiftDateOverride::query()->create([
                'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date, 'spots' => 1,
            ]);
        }
        $fixed = ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08', 'fixed' => true,
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-09', 'fixed' => false,
        ]);
        PlanningRule::create(['type' => 'equal_workload']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['id' => $fixed->id, 'employee_id' => $a->id]);
    }

    public function test_a_published_assignment_is_never_substituted_away(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach($shift);

        $a = $this->employee();
        $b = $this->employee();
        foreach (['2026-09-08', '2026-09-09'] as $date) {
            $this->makeAvailable($a, $shift, $date);
            $this->makeAvailable($b, $shift, $date);
            WorkcenterShiftDateOverride::query()->create([
                'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date, 'spots' => 1,
            ]);
        }
        $published = ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-09', 'fixed' => false,
        ]);
        PublishedWeek::query()->create(['week_start' => self::CYCLE_START, 'workcenter_id' => $workcenter->id]);
        PlanningRule::create(['type' => 'equal_workload']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['id' => $published->id, 'employee_id' => $a->id]);
    }
}
