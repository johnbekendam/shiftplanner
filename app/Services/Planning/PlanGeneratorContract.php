<?php

namespace App\Services\Planning;

use App\Models\PlanGenerationRun;

interface PlanGeneratorContract
{
    /**
     * Solve $run's cycle and write the result: diffs the solution against
     * the cycle's current non-locked assignments, applies the diff to
     * `shift_assignments`, and updates $run to `done` (or `failed`, on an
     * unexpected exception) with its `changes`/`unfulfilled` payloads.
     */
    public function generate(PlanGenerationRun $run): void;
}
