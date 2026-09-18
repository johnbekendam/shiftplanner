<?php

namespace Tests\Feature;

use App\Jobs\GeneratePlan;
use App\Models\PlanGenerationRun;
use App\Models\PlanningSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlanGenerationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    private function setPeriodStart(string $date = '2026-09-07'): void
    {
        PlanningSettings::current()->update(['period_start' => $date]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->post('/planning/cycles/2026-09-07/generate')->assertRedirect('/login');
    }

    public function test_manager_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriodStart();

        $this->post('/planning/cycles/2026-09-07/generate')->assertForbidden();
    }

    public function test_rejects_when_no_period_start_is_configured(): void
    {
        $this->actingAsAdmin();

        $this->post('/planning/cycles/2026-09-07/generate')->assertInvalid(['cycleStart']);
        $this->assertSame(0, PlanGenerationRun::count());
    }

    public function test_rejects_a_date_that_is_not_a_real_cycle_boundary(): void
    {
        $this->actingAsAdmin();
        $this->setPeriodStart('2026-09-07');

        // The cycle after 2026-09-07 starts 2026-09-21, not 2026-09-14.
        $this->post('/planning/cycles/2026-09-14/generate')->assertInvalid(['cycleStart']);
        $this->assertSame(0, PlanGenerationRun::count());
    }

    public function test_a_valid_cycle_creates_a_pending_run_and_dispatches_the_job(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->setPeriodStart('2026-09-07');

        $this->post('/planning/cycles/2026-09-07/generate')->assertRedirect();

        $run = PlanGenerationRun::sole();
        $this->assertSame('2026-09-07', $run->cycle_start->toDateString());
        $this->assertSame(PlanGenerationRun::STATUS_PENDING, $run->status);
        Queue::assertPushed(GeneratePlan::class);
    }

    public function test_a_later_cycle_in_the_sequence_is_also_valid(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->setPeriodStart('2026-09-07');

        // Cycles are 2-week blocks: 09-07, 09-21, 10-05, ...
        $this->post('/planning/cycles/2026-09-21/generate')->assertRedirect();

        $this->assertSame(1, PlanGenerationRun::count());
    }

    public function test_rejects_a_second_generate_while_one_is_already_active(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->setPeriodStart('2026-09-07');
        PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_RUNNING]);

        $this->post('/planning/cycles/2026-09-07/generate')->assertStatus(409);

        $this->assertSame(1, PlanGenerationRun::count());
        Queue::assertNotPushed(GeneratePlan::class);
    }

    public function test_a_new_run_is_allowed_once_the_previous_one_is_done(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->setPeriodStart('2026-09-07');
        PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_DONE]);

        $this->post('/planning/cycles/2026-09-07/generate')->assertRedirect();

        $this->assertSame(2, PlanGenerationRun::count());
    }
}
