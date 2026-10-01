<?php

namespace App\Http\Controllers;

use App\Models\PlanningSettings;
use App\Services\ScheduleWeek;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The schedule by its secret link, with no login. One token for the whole
 * application. See features/roster-public-link/.
 */
class SchedulePublicController extends Controller
{
    public function show(Request $request, string $token, ScheduleWeek $schedule)
    {
        abort_unless(hash_equals(PlanningSettings::current()->rosterToken(), $token), 404);

        return Inertia::render('SchedulePublic', $schedule->props($request))
            ->toResponse($request)
            ->withHeaders([
                'X-Robots-Tag' => 'noindex',
                'Cache-Control' => 'no-store, private',
            ]);
    }
}
