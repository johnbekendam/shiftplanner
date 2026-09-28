<?php

namespace App\Http\Controllers;

use App\Services\RosterWeek;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Read-only roster: who works on which day of one ISO week. Open to every
 * logged-in user. See features/roster/.
 */
class RosterController extends Controller
{
    public function index(Request $request, RosterWeek $roster)
    {
        return Inertia::render('Roster', $roster->props($request));
    }
}
