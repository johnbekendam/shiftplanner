<?php

namespace Tests\Feature\Planning;

use App\Models\PlanningRule;
use App\Models\Shift;
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
}
