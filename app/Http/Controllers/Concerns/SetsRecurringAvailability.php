<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Employee;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait SetsRecurringAvailability
{
    /**
     * Apply one grid cell. 'available' clears the cell; the other levels
     * upsert the single row for that weekday and shift.
     */
    protected function setCell(Request $request, Employee $employee, int $weekday, Shift $shift): void
    {
        $level = $request->validate([
            'level' => ['required', Rule::in(['available', ...RecurringAvailability::LEVELS])],
        ])['level'];

        $cell = $employee->recurringAvailabilities()
            ->where('weekday', $weekday)
            ->where('shift_id', $shift->id);

        if ($level === 'available') {
            $cell->delete();

            return;
        }

        $employee->recurringAvailabilities()->updateOrCreate(
            ['weekday' => $weekday, 'shift_id' => $shift->id],
            ['level' => $level],
        );
    }
}
