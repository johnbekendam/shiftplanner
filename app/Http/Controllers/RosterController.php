<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Read-only roster: who works on which day of one ISO week. Open to every
 * logged-in user. See features/roster/.
 */
class RosterController extends Controller
{
    public function index(Request $request)
    {
        $now = now();
        $weekStart = $this->weekStart($request, $now);
        $days = collect(range(0, 6))->map(fn (int $i) => $weekStart->copy()->addDays($i));

        return Inertia::render('Roster', [
            'weekStart' => $weekStart->toDateString(),
            'weekNumber' => $weekStart->isoWeek,
            'today' => $now->toDateString(),
            'days' => $days->map->toDateString()->all(),
        ]);
    }

    /** The Monday of the `?week=` date, or of the current week when it is missing or invalid. */
    private function weekStart(Request $request, Carbon $now): Carbon
    {
        $week = $request->query('week');
        $date = is_string($week) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)
            ? rescue(fn () => Carbon::createFromFormat('!Y-m-d', $week), report: false)
            : null;

        return ($date ?: $now->copy())->startOfWeek(Carbon::MONDAY)->startOfDay();
    }
}
