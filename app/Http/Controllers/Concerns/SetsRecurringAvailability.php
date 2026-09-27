<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Employee;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait SetsRecurringAvailability
{
    /** Apply one grid cell as an explicit availability level. */
    protected function setCell(Request $request, Employee $employee, int $weekday, Shift $shift): void
    {
        $level = $request->validate([
            'level' => ['required', Rule::in(['not_set', 'available', ...RecurringAvailability::LEVELS])],
        ])['level'];

        if (! $employee->effectiveShifts()->contains($shift)) {
            throw ValidationException::withMessages(['shift' => __('availability.error.shift_hidden')]);
        }

        // Clearing is always allowed, so a default left on a day the shift
        // no longer runs can still be removed.
        if ($level === 'not_set') {
            $employee->recurringAvailabilities()
                ->where('weekday', $weekday)
                ->where('shift_id', $shift->id)
                ->delete();

            return;
        }

        if (! in_array($weekday, $employee->shiftWeekdays()[$shift->id], true)) {
            throw ValidationException::withMessages(['shift' => __('availability.error.shift_not_running')]);
        }

        $employee->recurringAvailabilities()->updateOrCreate(
            ['weekday' => $weekday, 'shift_id' => $shift->id],
            ['level' => $level],
        );
    }
}
