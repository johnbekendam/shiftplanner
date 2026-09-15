<?php

namespace App\Http\Controllers;

use App\Models\PublishedWeek;

class PublishedWeekController extends Controller
{
    public function store(string $weekStart)
    {
        PublishedWeek::query()->firstOrCreate(['week_start' => $weekStart]);

        return back()->with('success', __('scheduling.flash.published'));
    }

    public function destroy(string $weekStart)
    {
        PublishedWeek::query()->whereDate('week_start', $weekStart)->delete();

        return back()->with('success', __('scheduling.flash.unpublished'));
    }
}
