<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DemandController extends Controller
{
    public function index(Request $request)
    {
        $workcenters = Workcenter::query()->whereNull('archived_at')->get();
        $workcenter = $workcenters->firstWhere('id', $request->integer('workcenter')) ?? $workcenters->first();

        return Inertia::render('Demand', [
            'workcenters' => $workcenters
                ->map(fn (Workcenter $workcenter) => ['id' => $workcenter->id, 'name' => $workcenter->name])
                ->values()
                ->all(),
            'workcenterId' => $workcenter?->id,
            'shifts' => Shift::all()
                ->map(fn (Shift $shift) => [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                ])
                ->all(),
            'defaults' => $workcenter ? $this->defaults($workcenter) : [],
            'overrides' => $workcenter ? $this->overrides($workcenter) : [],
            'assigned' => $workcenter ? $this->assigned($workcenter) : [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'workcenter_id' => ['required', 'integer', 'exists:workcenters,id', $this->notArchived()],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'spots' => ['required', 'array', 'size:7'],
            'spots.*' => ['integer', 'min:0'],
        ]);

        if ($this->isAssigned($data['workcenter_id'], $data['shift_id'])) {
            throw ValidationException::withMessages(['shift_id' => __('demand.error.duplicate_pair')]);
        }

        Workcenter::findOrFail($data['workcenter_id'])->shifts()->attach($data['shift_id']);
        $this->writeCapacity($data['workcenter_id'], $data['shift_id'], $data['spots']);

        return back()->with('success', __('demand.flash.saved'));
    }

    public function update(Request $request, int $workcenterId, int $shiftId)
    {
        if (! $this->isAssigned($workcenterId, $shiftId)) {
            abort(404);
        }

        $data = $request->validate([
            'spots' => ['required', 'array', 'size:7'],
            'spots.*' => ['integer', 'min:0'],
        ]);

        $this->writeCapacity($workcenterId, $shiftId, $data['spots']);

        return back()->with('success', __('demand.flash.saved'));
    }

    public function destroy(int $workcenterId, int $shiftId)
    {
        if (! $this->isAssigned($workcenterId, $shiftId)) {
            abort(404);
        }

        Workcenter::findOrFail($workcenterId)->shifts()->detach($shiftId);
        WorkcenterShiftCapacity::query()
            ->where('workcenter_id', $workcenterId)
            ->where('shift_id', $shiftId)
            ->delete();
        WorkcenterShiftDateOverride::query()
            ->where('workcenter_id', $workcenterId)
            ->where('shift_id', $shiftId)
            ->delete();

        return back()->with('success', __('demand.flash.saved'));
    }

    /** One entry per shift of the workcenter, spots for weekdays 1 (Monday) through 7 (Sunday), 0 where no row exists yet. */
    private function defaults(Workcenter $workcenter): array
    {
        $capacities = WorkcenterShiftCapacity::query()
            ->where('workcenter_id', $workcenter->id)
            ->get()
            ->groupBy('shift_id');

        return $workcenter->shifts
            ->map(fn (Shift $shift) => [
                'shift_id' => $shift->id,
                'spots' => collect(range(1, 7))
                    ->map(fn ($weekday) => $capacities->get($shift->id, collect())->firstWhere('weekday', $weekday)?->spots ?? 0)
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /** Every date override of the workcenter: [{ shift_id, date, spots }]. */
    private function overrides(Workcenter $workcenter): array
    {
        return WorkcenterShiftDateOverride::query()
            ->where('workcenter_id', $workcenter->id)
            ->orderBy('date')
            ->get()
            ->map(fn (WorkcenterShiftDateOverride $override) => [
                'shift_id' => $override->shift_id,
                'date' => $override->date->toDateString(),
                'spots' => $override->spots,
            ])
            ->all();
    }

    /** The number of planned assignments per shift and date: [{ shift_id, date, count }]. */
    private function assigned(Workcenter $workcenter): array
    {
        return ShiftAssignment::query()
            ->where('workcenter_id', $workcenter->id)
            ->selectRaw('shift_id, date, count(*) as count')
            ->groupBy('shift_id', 'date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'shift_id' => $row->shift_id,
                'date' => $row->date->toDateString(),
                'count' => (int) $row->count,
            ])
            ->all();
    }

    private function isAssigned(int $workcenterId, int $shiftId): bool
    {
        return Workcenter::query()
            ->whereKey($workcenterId)
            ->whereHas('shifts', fn ($q) => $q->whereKey($shiftId))
            ->exists();
    }

    /** Upserts all seven weekday rows for one (workcenter, shift) pair. */
    private function writeCapacity(int $workcenterId, int $shiftId, array $spots): void
    {
        foreach (array_values($spots) as $index => $value) {
            WorkcenterShiftCapacity::query()->updateOrCreate(
                ['workcenter_id' => $workcenterId, 'shift_id' => $shiftId, 'weekday' => $index + 1],
                ['spots' => $value],
            );
        }
    }

    private function notArchived(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $archived = Workcenter::query()
                ->whereKey($value)
                ->whereNotNull('archived_at')
                ->exists();

            if ($archived) {
                $fail(__('demand.error.workcenter_archived'));
            }
        };
    }
}
