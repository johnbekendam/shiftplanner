<?php

namespace Tests\Feature\Planning;

use App\Models\Competence;
use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetenceRequiredRuleTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    public function test_soft_competence_required_prefers_the_candidate_who_holds_it(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->openCell($workcenter, $shift, '2026-09-08');
        $competence = Competence::factory()->create();

        $holder = $this->employee();
        $holder->competences()->attach($competence->id);
        $this->makeAvailable($holder, $shift, '2026-09-08');
        $nonHolder = $this->employee();
        $this->makeAvailable($nonHolder, $shift, '2026-09-08');

        PlanningRule::create([
            'type' => 'competence_required', 'mode' => 'soft', 'severity' => 10,
            'config' => ['workcenter_id' => $workcenter->id, 'competence_id' => $competence->id],
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $holder->id, 'date' => '2026-09-08']);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $nonHolder->id]);
    }
}
