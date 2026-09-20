<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePlan;
use App\Models\PlanGenerationRun;
use App\Services\Planning\PlanningCycle;
use Illuminate\Validation\ValidationException;

class PlanGenerationController extends Controller
{
    /** One click generates every 2-week cycle across the whole planning period, one run each. */
    public function store()
    {
        $cycles = PlanningCycle::allWithinPeriod();

        if ($cycles === []) {
            throw ValidationException::withMessages(['period' => __('planning.error.no_period_start')]);
        }

        $cycleStarts = array_map(fn ($cycle) => $cycle->toDateString(), $cycles);

        if (PlanGenerationRun::query()
            ->whereIn('cycle_start', $cycleStarts)
            ->whereIn('status', PlanGenerationRun::ACTIVE_STATUSES)
            ->exists()) {
            abort(409, __('planning.error.generation_in_progress'));
        }

        foreach ($cycleStarts as $cycleStart) {
            $run = PlanGenerationRun::create([
                'cycle_start' => $cycleStart,
                'status' => PlanGenerationRun::STATUS_PENDING,
            ]);

            GeneratePlan::dispatch($run->id);
        }

        return back()->with('success', __('planning.flash.generation_started'));
    }
}
