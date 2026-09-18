<?php

namespace App\Jobs;

use App\Models\PlanGenerationRun;
use App\Services\Planning\PlanGeneratorContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GeneratePlan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $runId) {}

    public function handle(PlanGeneratorContract $generator): void
    {
        $run = PlanGenerationRun::findOrFail($this->runId);
        $run->update(['status' => PlanGenerationRun::STATUS_RUNNING]);

        $generator->generate($run);
    }

    /** Only reached once every retry is exhausted — leaves the cycle's assignments untouched. */
    public function failed(Throwable $exception): void
    {
        PlanGenerationRun::whereKey($this->runId)->update([
            'status' => PlanGenerationRun::STATUS_FAILED,
            'error' => $exception->getMessage(),
        ]);
    }
}
