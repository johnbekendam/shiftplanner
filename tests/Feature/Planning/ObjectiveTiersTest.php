<?php

namespace Tests\Feature\Planning;

use App\Models\Shift;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftDateOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObjectiveTiersTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    public function test_most_constrained_cell_is_filled_before_an_easier_one_starves_it(): void
    {
        // Two cells same date: A has 2 eligible candidates, B has only 1 (the same
        // one A could also use). Filling A first with its "only" option would strand
        // B; most-constrained-first must fill B before A takes B's only candidate.
        [$workcenterA, $shiftA] = $this->workcenterWithOverride('2026-09-08');
        $workcenterB = Workcenter::factory()->create();
        $shiftB = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $workcenterB->shifts()->attach($shiftB);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenterB->id, 'shift_id' => $shiftB->id, 'date' => '2026-09-08', 'spots' => 1,
        ]);

        $shared = $this->eligibleEmployee($shiftA, '2026-09-08');
        $this->makeAvailable($shared, $shiftB, '2026-09-08');
        $onlyForA = $this->eligibleEmployee($shiftA, '2026-09-08');
        // $shared is the only candidate eligible for B (hard-restricted to workcenter B);
        // A has both $shared and $onlyForA available.
        $shared->workcenters()->attach($workcenterB->id, ['mode' => 'hard']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', [
            'employee_id' => $shared->id, 'workcenter_id' => $workcenterB->id, 'shift_id' => $shiftB->id, 'date' => '2026-09-08',
        ]);
        $this->assertDatabaseHas('shift_assignments', [
            'employee_id' => $onlyForA->id, 'workcenter_id' => $workcenterA->id, 'shift_id' => $shiftA->id, 'date' => '2026-09-08',
        ]);
        $this->assertSame([], $run->refresh()->unfulfilled);
    }
}
