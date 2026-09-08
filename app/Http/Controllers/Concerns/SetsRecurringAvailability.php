<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Employee;
use App\Models\RecurringAvailability;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait SetsRecurringAvailability
{
    /**
     * Apply one grid cell. 'available' clears the cell; the other levels
     * upsert the single row for that weekday and daypart.
     */
    protected function setCell(Request $request, Employee $employee, int $weekday, string $daypart): void
    {
        $level = $request->validate([
            'level' => ['required', Rule::in(['available', ...RecurringAvailability::LEVELS])],
        ])['level'];

        $cell = $employee->recurringAvailabilities()
            ->where('weekday', $weekday)
            ->where('daypart', $daypart);

        if ($level === 'available') {
            $cell->delete();

            return;
        }

        $employee->recurringAvailabilities()->updateOrCreate(
            ['weekday' => $weekday, 'daypart' => $daypart],
            ['level' => $level],
        );
    }
}
