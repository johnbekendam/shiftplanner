<?php

namespace App\Http\Controllers;

use App\Models\PublishedWeek;
use App\Models\Workcenter;
use Illuminate\Http\Request;

class PlannerOpenWeekController extends Controller
{
    /** Allows or freezes the autoplanner for one published (week, workcenter) pair. */
    public function update(Request $request, string $weekStart, Workcenter $workcenter)
    {
        $data = $request->validate(['planner_open' => ['required', 'boolean']]);

        $published = PublishedWeek::query()
            ->whereDate('week_start', $weekStart)
            ->where('workcenter_id', $workcenter->id)
            ->firstOrFail();

        $published->update(['planner_open' => $data['planner_open']]);

        return back()->with('success', __($data['planner_open'] ? 'scheduling.flash.planner_allowed' : 'scheduling.flash.planner_frozen'));
    }
}
