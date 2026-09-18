<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PlanGenerationRun;
use App\Models\PlanningSettings;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanClearControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    private function setPeriod(string $start = '2026-09-07', string $end = '2026-09-20'): void
    {
        PlanningSettings::current()->update(['period_start' => $start, 'period_end' => $end]);
    }

    private function assignment(array $overrides = []): ShiftAssignment
    {
        return ShiftAssignment::factory()->create(array_merge([
            'employee_id' => Employee::factory(),
            'workcenter_id' => Workcenter::factory(),
            'shift_id' => Shift::factory(),
            'date' => '2026-09-08',
            'fixed' => false,
        ], $overrides));
    }

    public function test_guest_is_redirected(): void
    {
        $this->delete('/planning/clear')->assertRedirect('/login');
    }

    public function test_manager_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod();

        $this->delete('/planning/clear')->assertForbidden();
    }

    public function test_rejects_when_no_period_is_configured(): void
    {
        $this->actingAsAdmin();
        $assignment = $this->assignment();

        $this->delete('/planning/clear')->assertInvalid(['period']);

        $this->assertDatabaseHas('shift_assignments', ['id' => $assignment->id]);
    }

    public function test_deletes_an_unfixed_unpublished_assignment_within_the_period(): void
    {
        $this->actingAsAdmin();
        $this->setPeriod();
        $assignment = $this->assignment(['date' => '2026-09-08', 'fixed' => false]);

        $this->delete('/planning/clear')->assertRedirect();

        $this->assertDatabaseMissing('shift_assignments', ['id' => $assignment->id]);
    }

    public function test_leaves_a_fixed_assignment_untouched(): void
    {
        $this->actingAsAdmin();
        $this->setPeriod();
        $assignment = $this->assignment(['date' => '2026-09-08', 'fixed' => true]);

        $this->delete('/planning/clear')->assertRedirect();

        $this->assertDatabaseHas('shift_assignments', ['id' => $assignment->id]);
    }

    public function test_leaves_a_published_assignment_untouched(): void
    {
        $this->actingAsAdmin();
        $this->setPeriod();
        $workcenter = Workcenter::factory()->create();
        $assignment = $this->assignment(['date' => '2026-09-08', 'workcenter_id' => $workcenter->id, 'fixed' => false]);
        PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $workcenter->id]);

        $this->delete('/planning/clear')->assertRedirect();

        $this->assertDatabaseHas('shift_assignments', ['id' => $assignment->id]);
    }

    public function test_leaves_an_assignment_outside_the_periods_cycle_range_untouched(): void
    {
        $this->actingAsAdmin();
        $this->setPeriod('2026-09-07', '2026-09-20'); // one cycle: 2026-09-07..09-20
        $assignment = $this->assignment(['date' => '2026-10-05', 'fixed' => false]); // the next cycle, outside the period

        $this->delete('/planning/clear')->assertRedirect();

        $this->assertDatabaseHas('shift_assignments', ['id' => $assignment->id]);
    }

    public function test_a_cycle_that_extends_past_period_end_still_clears_in_full(): void
    {
        $this->actingAsAdmin();
        // 09-07..09-25 covers cycles 09-07 and 09-21; the second cycle's own end
        // (10-04) runs past period_end, but it still clears in full, unbounded.
        $this->setPeriod('2026-09-07', '2026-09-25');
        $assignment = $this->assignment(['date' => '2026-10-03', 'fixed' => false]);

        $this->delete('/planning/clear')->assertRedirect();

        $this->assertDatabaseMissing('shift_assignments', ['id' => $assignment->id]);
    }

    public function test_leaves_an_assignment_for_an_archived_workcenter_untouched(): void
    {
        $this->actingAsAdmin();
        $this->setPeriod();
        $workcenter = Workcenter::factory()->create(['archived_at' => now()]);
        $assignment = $this->assignment(['date' => '2026-09-08', 'workcenter_id' => $workcenter->id, 'fixed' => false]);

        $this->delete('/planning/clear')->assertRedirect();

        $this->assertDatabaseHas('shift_assignments', ['id' => $assignment->id]);
    }

    public function test_rejects_while_any_cycle_in_the_period_has_an_active_run(): void
    {
        $this->actingAsAdmin();
        $this->setPeriod('2026-09-07', '2026-10-04'); // cycles: 09-07, 09-21
        $assignment = $this->assignment(['date' => '2026-09-08', 'fixed' => false]);
        PlanGenerationRun::create(['cycle_start' => '2026-09-21', 'status' => PlanGenerationRun::STATUS_RUNNING]);

        $this->delete('/planning/clear')->assertStatus(409);

        $this->assertDatabaseHas('shift_assignments', ['id' => $assignment->id]);
    }

    public function test_does_not_touch_plan_generation_run_history(): void
    {
        $this->actingAsAdmin();
        $this->setPeriod();
        $this->assignment(['date' => '2026-09-08', 'fixed' => false]);
        $run = PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_DONE]);

        $this->delete('/planning/clear')->assertRedirect();

        $this->assertDatabaseHas('plan_generation_runs', ['id' => $run->id]);
    }
}
