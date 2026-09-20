<?php

namespace App\Http\Controllers;

use App\Models\PlanGenerationRun;
use App\Models\PublishedWeek;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Services\Planning\PlanningCycle;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PlanClearController extends Controller
{
    /**
     * Deletes every unfixed, unpublished assignment across the whole
     * planning period — the same range and the same "locked" test Generate
     * itself uses, so this only ever removes what a Generate run would
     * already feel free to move.
     */
    public function destroy()
    {
        $cycles = PlanningCycle::allWithinPeriod();

        if ($cycles === []) {
            throw ValidationException::withMessages(['period' => __('planning.error.no_period_start')]);
        }

        $rangeStart = $cycles[0];
        $rangeEnd = end($cycles)->copy()->addDays(13);
        $cycleStarts = array_map(fn (Carbon $cycle) => $cycle->toDateString(), $cycles);

        if (PlanGenerationRun::query()
            ->whereIn('cycle_start', $cycleStarts)
            ->whereIn('status', PlanGenerationRun::ACTIVE_STATUSES)
            ->exists()) {
            abort(409, __('planning.error.generation_in_progress'));
        }

        $workcenterIds = Workcenter::query()->whereNull('archived_at')->pluck('id');
        $lockedPairs = PublishedWeek::lockedPairs($rangeStart, $rangeEnd, $workcenterIds);

        $toDelete = ShiftAssignment::query()
            ->where('fixed', false)
            ->whereIn('workcenter_id', $workcenterIds)
            ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->get()
            ->reject(fn (ShiftAssignment $a) => $lockedPairs->has(
                "{$a->date->copy()->startOfWeek(Carbon::MONDAY)->toDateString()}:{$a->workcenter_id}"
            ));

        ShiftAssignment::query()->whereIn('id', $toDelete->pluck('id'))->delete();

        return back()->with('success', __('planning.flash.cleared'));
    }
}
