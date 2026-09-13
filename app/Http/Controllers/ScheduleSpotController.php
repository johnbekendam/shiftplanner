<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftDateOverride;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScheduleSpotController extends Controller
{
    public function update(Request $request, Workcenter $workcenter, Shift $shift, string $date)
    {
        $data = $request->validate(['spots' => ['required', 'integer', 'min:0']]);

        $assignedCount = ShiftAssignment::query()
            ->where('workcenter_id', $workcenter->id)
            ->where('shift_id', $shift->id)
            ->whereDate('date', $date)
            ->count();

        if ($data['spots'] < $assignedCount) {
            throw ValidationException::withMessages(['spots' => __('scheduling.error.spots_below_assigned')]);
        }

        WorkcenterShiftDateOverride::query()->updateOrCreate(
            ['workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date],
            ['spots' => $data['spots']],
        );

        return back()->with('success', __('scheduling.flash.updated'));
    }

    public function destroy(Workcenter $workcenter, Shift $shift, string $date)
    {
        WorkcenterShiftDateOverride::query()
            ->where('workcenter_id', $workcenter->id)
            ->where('shift_id', $shift->id)
            ->whereDate('date', $date)
            ->delete();

        return back()->with('success', __('scheduling.flash.updated'));
    }
}
