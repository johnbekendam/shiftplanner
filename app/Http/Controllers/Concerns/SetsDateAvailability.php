<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AvailabilityOverride;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait SetsDateAvailability
{
    /**
     * Replace the whole-day block and the shift levels of one date. An
     * omitted shift follows the weekly default. Only shifts the employee
     * sees on that date are replaced: rows of other shifts (hidden, or not
     * running that day) stay, and the payload may repeat them unchanged.
     *
     * @return array{0: array, 1: array} the date's state before and after
     */
    protected function setDate(Request $request, Employee $employee, string $date): array
    {
        // The route only checks the Y-m-d shape; 2026-02-30 is not a date.
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        abort_unless(checkdate($month, $day, $year), 404);

        $data = $request->validate([
            'blocked' => ['required', 'boolean'],
            'shifts' => ['present', 'array'],
            'shifts.*' => ['required', Rule::in(AvailabilityOverride::LEVELS)],
        ]);

        $effectiveIds = $employee->effectiveShifts()->pluck('id')->all();
        $editableIds = $this->editableShiftIds($employee, $date, $effectiveIds);
        $stored = $employee->availabilityOverrides()->whereDate('date', $date)->whereNotNull('shift_id')
            ->pluck('level', 'shift_id')->all();

        $shifts = [];
        foreach ($data['shifts'] as $shiftId => $level) {
            $shiftId = (int) $shiftId;
            if (in_array($shiftId, $editableIds, true)) {
                $shifts[$shiftId] = $level;
            } elseif (($stored[$shiftId] ?? null) !== $level) {
                $error = in_array($shiftId, $effectiveIds, true) ? 'shift_not_running' : 'shift_hidden';
                throw ValidationException::withMessages(['shifts' => __("availability.error.{$error}")]);
            }
        }

        $before = $this->dateState($employee, $date);

        DB::transaction(function () use ($employee, $date, $data, $shifts, $editableIds) {
            $employee->availabilityOverrides()
                ->whereDate('date', $date)
                ->where(fn ($q) => $q->whereNull('shift_id')->orWhereIn('shift_id', $editableIds))
                ->delete();

            if ($data['blocked']) {
                $employee->availabilityOverrides()->create(['date' => $date, 'shift_id' => null, 'level' => 'unavailable']);
            }

            foreach ($shifts as $shiftId => $level) {
                $employee->availabilityOverrides()->create(['date' => $date, 'shift_id' => $shiftId, 'level' => $level]);
            }
        });

        return [$before, $this->dateState($employee, $date)];
    }

    /**
     * The effective shifts that run on the date: the ones its schedule shows.
     *
     * @param  int[]  $effectiveIds
     * @return int[]
     */
    private function editableShiftIds(Employee $employee, string $date, array $effectiveIds): array
    {
        $weekday = Carbon::parse($date)->isoWeekday();
        $shiftWeekdays = $employee->shiftWeekdays();

        return array_values(array_filter(
            $effectiveIds,
            fn (int $id) => in_array($weekday, $shiftWeekdays[$id] ?? [], true),
        ));
    }

    /** The audit shape of one date: its block flag and shift levels. */
    private function dateState(Employee $employee, string $date): array
    {
        $rows = $employee->availabilityOverrides()->whereDate('date', $date)->get();

        return [
            'date' => $date,
            'blocked' => $rows->contains(fn ($row) => $row->shift_id === null),
            'shifts' => $rows->whereNotNull('shift_id')->mapWithKeys(fn ($row) => [$row->shift_id => $row->level])->all(),
        ];
    }
}
