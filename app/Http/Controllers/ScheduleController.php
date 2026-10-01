<?php

namespace App\Http\Controllers;

use App\Services\ScheduleWeek;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Read-only schedule: who works on which day of one ISO week. Open to every
 * logged-in user. See features/roster/ and features/schedule-rename/.
 */
class ScheduleController extends Controller
{
    public function index(Request $request, ScheduleWeek $schedule)
    {
        return Inertia::render('Schedule', $schedule->props($request));
    }
}
