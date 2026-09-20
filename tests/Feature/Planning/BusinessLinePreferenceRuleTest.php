<?php

namespace Tests\Feature\Planning;

use App\Models\BusinessLine;
use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessLinePreferenceRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    public function test_soft_business_line_preference_prefers_the_matching_candidate(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->openCell($workcenter, $shift, '2026-09-08');
        $line = BusinessLine::factory()->create();

        $matching = $this->employee(['business_line_id' => $line->id]);
        $this->makeAvailable($matching, $shift, '2026-09-08');
        $other = $this->employee();
        $this->makeAvailable($other, $shift, '2026-09-08');

        PlanningRule::create([
            'type' => 'business_line_preference', 'mode' => 'soft', 'severity' => 10,
            'config' => ['workcenter_id' => $workcenter->id, 'business_line_id' => $line->id],
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $matching->id, 'date' => '2026-09-08']);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $other->id]);
    }
}
