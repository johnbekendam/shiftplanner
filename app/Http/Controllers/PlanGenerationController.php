<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePlan;
use App\Models\PlanGenerationRun;
use App\Services\Planning\PlanningCycle;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PlanGenerationController extends Controller
{
    public function store(string $cycleStart)
    {
        $date = Carbon::parse($cycleStart);
        $resolved = PlanningCycle::containing($date);

        if ($resolved === null) {
            throw ValidationException::withMessages(['cycleStart' => __('planning.error.no_period_start')]);
        }

        if (! $resolved->isSameDay($date)) {
            throw ValidationException::withMessages(['cycleStart' => __('planning.error.invalid_cycle')]);
        }

        if (PlanGenerationRun::query()
            ->whereDate('cycle_start', $date)
            ->whereIn('status', PlanGenerationRun::ACTIVE_STATUSES)
            ->exists()) {
            abort(409, __('planning.error.generation_in_progress'));
        }

        $run = PlanGenerationRun::create([
            'cycle_start' => $date->toDateString(),
            'status' => PlanGenerationRun::STATUS_PENDING,
        ]);

        GeneratePlan::dispatch($run->id);

        return back()->with('success', __('planning.flash.generation_started'));
    }
}
