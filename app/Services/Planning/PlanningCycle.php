<?php

namespace App\Services\Planning;

use App\Models\PlanningSettings;
use Carbon\Carbon;

/**
 * The 2-week cycle boundary math `planning-rules` already defined: cycles
 * are non-overlapping 2-week blocks starting from the Monday of the week
 * containing `PlanningSettings.period_start`. `containing()` is used by the
 * trigger UI to know which cycle the viewed week belongs to (so its own
 * generation run — change summary, unfulfilled reasons — can be shown
 * against that week specifically); `allWithinPeriod()` is what Generate
 * itself now iterates, one run per cycle across the whole planning period.
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

        return self::cycleIndex($periodStart, $date);
    }

    /**
     * Every cycle start from the period's anchor up to (and including) the
     * last cycle that starts on or before `period_end` — a cycle that starts
     * within the period runs in full even if its second week extends past
     * `period_end`, since cycles are the fairness unit and aren't truncated.
     * Empty when either boundary isn't configured yet.
     *
     * @return array<int, Carbon>
     */
    public static function allWithinPeriod(): array
    {
        $settings = PlanningSettings::current();
        $periodStart = $settings->period_start;
        $periodEnd = $settings->period_end;
        if ($periodStart === null || $periodEnd === null) {
            return [];
        }

        $anchor = $periodStart->copy()->startOfWeek(Carbon::MONDAY);
        $cycles = [];
        $cursor = $anchor->copy();
        while ($cursor->lte($periodEnd)) {
            $cycles[] = $cursor->copy();
            $cursor->addWeeks(2);
        }

        return $cycles;
    }

    private static function cycleIndex(Carbon $periodStart, Carbon $date): Carbon
    {
        $anchor = $periodStart->copy()->startOfWeek(Carbon::MONDAY);
        $weeksSinceAnchor = (int) floor($anchor->diffInDays($date, false) / 7);
        $cycleIndex = (int) floor($weeksSinceAnchor / 2);

        return $anchor->copy()->addWeeks($cycleIndex * 2);
    }
}
