<?php

namespace App\Http\Controllers;

use App\Models\PublishedWeek;
use App\Models\Workcenter;

class PublishedWeekController extends Controller
{
    public function store(string $weekStart, Workcenter $workcenter)
    {
        PublishedWeek::query()->firstOrCreate([
            'week_start' => $weekStart,
            'workcenter_id' => $workcenter->id,
        ]);

        return back()->with('success', __('scheduling.flash.published'));
    }

    public function destroy(string $weekStart, Workcenter $workcenter)
    {
        PublishedWeek::query()
            ->whereDate('week_start', $weekStart)
            ->where('workcenter_id', $workcenter->id)
            ->delete();

        return back()->with('success', __('scheduling.flash.unpublished'));
    }
}
