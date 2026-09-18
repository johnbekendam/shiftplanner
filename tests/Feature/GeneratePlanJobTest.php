<?php

namespace Tests\Feature;

use App\Jobs\GeneratePlan;
use App\Models\PlanGenerationRun;
use App\Services\Planning\PlanGeneratorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GeneratePlanJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_marks_the_run_running_before_calling_the_generator(): void
    {
        $run = PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_PENDING]);
        $fake = new class implements PlanGeneratorContract
        {
            public ?string $statusSeenAtCallTime = null;

            public function generate(PlanGenerationRun $run): void
            {
                $this->statusSeenAtCallTime = $run->status;
                $run->update(['status' => PlanGenerationRun::STATUS_DONE]);
            }
        };

        (new GeneratePlan($run->id))->handle($fake);

        $this->assertSame(PlanGenerationRun::STATUS_RUNNING, $fake->statusSeenAtCallTime);
        $this->assertSame(PlanGenerationRun::STATUS_DONE, $run->fresh()->status);
    }

    public function test_failed_marks_the_run_failed_with_the_error_message(): void
    {
        $run = PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_RUNNING]);

        (new GeneratePlan($run->id))->failed(new RuntimeException('solver exploded'));

        $run->refresh();
        $this->assertSame(PlanGenerationRun::STATUS_FAILED, $run->status);
        $this->assertSame('solver exploded', $run->error);
    }
}
