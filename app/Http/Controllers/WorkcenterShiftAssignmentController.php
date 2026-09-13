<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WorkcenterShiftAssignmentController extends Controller
{
    public function index()
    {
        return Inertia::render('WorkcenterShifts', [
            'workcenters' => Workcenter::query()
                ->whereNull('archived_at')
                ->get()
                ->map(fn (Workcenter $workcenter) => ['id' => $workcenter->id, 'name' => $workcenter->name])
                ->all(),
            'shifts' => Shift::all()
                ->map(fn (Shift $shift) => [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                ])
                ->all(),
            'assignments' => $this->assignments(),
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
            throw ValidationException::withMessages(['shift_id' => __('workcenter_shifts.error.duplicate_pair')]);
        }

        Workcenter::findOrFail($data['workcenter_id'])->shifts()->attach($data['shift_id']);
        $this->writeCapacity($data['workcenter_id'], $data['shift_id'], $data['spots']);

        return back()->with('success', __('workcenter_shifts.flash.saved'));
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

        return back()->with('success', __('workcenter_shifts.flash.saved'));
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

        return back()->with('success', __('workcenter_shifts.flash.saved'));
    }

    /** One entry per existing assignment, spots for weekdays 1 (Monday) through 7 (Sunday), 0 where no row exists yet. */
    private function assignments(): array
    {
        $capacitiesByPair = WorkcenterShiftCapacity::all()->groupBy(fn ($row) => "{$row->workcenter_id}:{$row->shift_id}");

        return Workcenter::query()
            ->with('shifts')
            ->get()
            ->flatMap(fn (Workcenter $workcenter) => $workcenter->shifts->map(fn (Shift $shift) => [
                'workcenter_id' => $workcenter->id,
                'shift_id' => $shift->id,
                'spots' => collect(range(1, 7))->map(function ($weekday) use ($capacitiesByPair, $workcenter, $shift) {
                    $row = $capacitiesByPair->get("{$workcenter->id}:{$shift->id}", collect())
                        ->firstWhere('weekday', $weekday);

                    return $row?->spots ?? 0;
                })->all(),
            ]))
            ->values()
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
                $fail(__('workcenter_shifts.error.workcenter_archived'));
            }
        };
    }
}
