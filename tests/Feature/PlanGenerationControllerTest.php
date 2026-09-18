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

    private function setPeriod(string $start = '2026-09-07', string $end = '2026-10-04'): void
    {
        PlanningSettings::current()->update(['period_start' => $start, 'period_end' => $end]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->post('/planning/generate')->assertRedirect('/login');
    }

    public function test_manager_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod();

        $this->post('/planning/generate')->assertForbidden();
    }

    public function test_rejects_when_no_period_is_configured(): void
    {
        $this->actingAsAdmin();

        $this->post('/planning/generate')->assertInvalid(['period']);
        $this->assertSame(0, PlanGenerationRun::count());
    }

    public function test_rejects_when_only_the_period_start_is_configured(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07', 'period_end' => null]);

        $this->post('/planning/generate')->assertInvalid(['period']);
        $this->assertSame(0, PlanGenerationRun::count());
    }

    public function test_creates_one_pending_run_and_dispatches_one_job_per_cycle_in_the_period(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        // 09-07..09-25 spans two cycle starts (09-07, 09-21) — the second cycle's own
        // end (10-04) runs past period_end, but it still generates in full since a
        // cycle that starts within the period is never truncated.
        $this->setPeriod('2026-09-07', '2026-09-25');

        $this->post('/planning/generate')->assertRedirect();

        $this->assertSame(2, PlanGenerationRun::count());
        $this->assertEqualsCanonicalizing(
            ['2026-09-07', '2026-09-21'],
            PlanGenerationRun::pluck('cycle_start')->map(fn ($d) => $d->toDateString())->all(),
        );
        $this->assertSame(2, PlanGenerationRun::query()->where('status', PlanGenerationRun::STATUS_PENDING)->count());
        Queue::assertPushed(GeneratePlan::class, 2);
    }

    public function test_a_period_shorter_than_one_cycle_still_generates_its_single_cycle(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->setPeriod('2026-09-07', '2026-09-10');

        $this->post('/planning/generate')->assertRedirect();

        $this->assertSame(1, PlanGenerationRun::count());
        Queue::assertPushed(GeneratePlan::class, 1);
    }

    public function test_rejects_a_second_generate_while_any_cycle_in_the_period_is_already_active(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->setPeriod('2026-09-07', '2026-10-04');
        PlanGenerationRun::create(['cycle_start' => '2026-09-21', 'status' => PlanGenerationRun::STATUS_RUNNING]);

        $this->post('/planning/generate')->assertStatus(409);

        $this->assertSame(1, PlanGenerationRun::count());
        Queue::assertNotPushed(GeneratePlan::class);
    }

    public function test_an_active_run_outside_the_current_period_does_not_block_a_new_generate(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->setPeriod('2026-09-07', '2026-09-10'); // one cycle: 2026-09-07 only
        PlanGenerationRun::create(['cycle_start' => '2026-10-05', 'status' => PlanGenerationRun::STATUS_RUNNING]);

        $this->post('/planning/generate')->assertRedirect();

        $this->assertSame(2, PlanGenerationRun::count());
        Queue::assertPushed(GeneratePlan::class, 1);
    }

    public function test_a_new_generate_is_allowed_once_every_cycle_in_the_period_is_resolved(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->setPeriod('2026-09-07', '2026-09-10'); // one cycle: 2026-09-07 only
        PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_DONE]);

        $this->post('/planning/generate')->assertRedirect();

        $this->assertSame(2, PlanGenerationRun::count());
    }
}
