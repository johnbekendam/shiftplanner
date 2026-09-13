<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkcenterShiftController extends Controller
{
    /** Replaces the full set of shifts attached to a workcenter. */
    public function update(Request $request, Workcenter $workcenter)
    {
        $data = $request->validate([
            'shift_ids' => ['present', 'array'],
            'shift_ids.*' => ['integer', 'exists:shifts,id'],
        ]);

        $currentIds = $workcenter->shifts()->pluck('shifts.id')->all();
        $wantedIds = collect($data['shift_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();

        $toAttach = array_values(array_diff($wantedIds, $currentIds));
        $toDetach = array_values(array_diff($currentIds, $wantedIds));

        $workcenter->shifts()->attach($toAttach);

        foreach ($toAttach as $shiftId) {
            $rows = collect(range(1, 7))->map(fn ($weekday) => [
                'workcenter_id' => $workcenter->id,
                'shift_id' => $shiftId,
                'weekday' => $weekday,
                'spots' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();
            WorkcenterShiftCapacity::query()->insert($rows);
        }

        if ($toDetach !== []) {
            $workcenter->shifts()->detach($toDetach);
            WorkcenterShiftCapacity::query()
                ->where('workcenter_id', $workcenter->id)
                ->whereIn('shift_id', $toDetach)
                ->delete();
            WorkcenterShiftDateOverride::query()
                ->where('workcenter_id', $workcenter->id)
                ->whereIn('shift_id', $toDetach)
                ->delete();
        }

        return back()->with('success', __('workcenters.flash.updated'));
    }

    /** Replaces the seven weekday-default spot counts for one attached shift. */
    public function updateCapacity(Request $request, Workcenter $workcenter, Shift $shift)
    {
        if (! $workcenter->shifts()->whereKey($shift->id)->exists()) {
            throw ValidationException::withMessages(['shift' => __('workcenters.error.shift_not_attached')]);
        }

        $data = $request->validate([
            'spots' => ['required', 'array', 'size:7'],
            'spots.*' => ['integer', 'min:0'],
        ]);

        foreach (array_values($data['spots']) as $index => $spots) {
            WorkcenterShiftCapacity::query()->updateOrCreate(
                ['workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => $index + 1],
                ['spots' => $spots],
            );
        }

        return back()->with('success', __('workcenters.flash.updated'));
    }
}
