<?php

namespace App\Http\Controllers;

use App\Models\PlanningSettings;
use Illuminate\Http\Request;

class PeriodController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'fte_hours' => ['required', 'integer', 'min:1'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'allow_employee_changes' => ['required', 'boolean'],
        ]);

        PlanningSettings::current()->update($data);

        return back()->with('success', __('period.flash.saved'));
    }
}
