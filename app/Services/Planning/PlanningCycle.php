<?php

namespace App\Services\Planning;

use App\Models\PlanningSettings;
use Carbon\Carbon;

/**
 * The 2-week cycle boundary math `planning-rules` already defined: cycles
 * are non-overlapping 2-week blocks starting from the Monday of the week
 * containing `PlanningSettings.period_start`. Shared by the trigger UI
 * (which cycle does the viewed week belong to) and the generate endpoint
 * (is the given cycle start actually a real cycle boundary).
 */
class PlanningCycle
{
    /** Null when no period_start is set yet — there's no anchor to compute cycles from. */
    public static function containing(Carbon $date): ?Carbon
    {
        $periodStart = PlanningSettings::current()->period_start;
        if ($periodStart === null) {
            return null;
        }

        $anchor = $periodStart->copy()->startOfWeek(Carbon::MONDAY);
        $weeksSinceAnchor = (int) floor($anchor->diffInDays($date, false) / 7);
        $cycleIndex = (int) floor($weeksSinceAnchor / 2);

        return $anchor->copy()->addWeeks($cycleIndex * 2);
    }
}
