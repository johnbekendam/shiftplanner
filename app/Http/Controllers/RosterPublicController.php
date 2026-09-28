<?php

namespace App\Http\Controllers;

use App\Models\PlanningSettings;
use App\Services\RosterWeek;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The roster by its secret link, with no login. One token for the whole
 * application. See features/roster-public-link/.
 */
class RosterPublicController extends Controller
{
    public function show(Request $request, string $token, RosterWeek $roster)
    {
        abort_unless(hash_equals(PlanningSettings::current()->rosterToken(), $token), 404);

        return Inertia::render('RosterPublic', $roster->props($request))
            ->toResponse($request)
            ->withHeaders([
                'X-Robots-Tag' => 'noindex',
                'Cache-Control' => 'no-store, private',
            ]);
    }
}
