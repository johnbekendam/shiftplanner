<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use Carbon\Carbon;

/**
 * Shared holiday/unavailable/overlap checks, used both to filter the
 * eligible-employee list and to re-validate a manual assignment write.
 */
class SchedulingEligibility
{
    public function isOnHoliday(Employee $employee, Carbon $date): bool
    {
        return $employee->holidays()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    public function isUnavailable(Employee $employee, int $weekday, Shift $shift): bool
    {
        return $this->recurringLevel($employee, $weekday, $shift) === RecurringAvailability::LEVELS[1];
    }

    public function isNotPreferred(Employee $employee, int $weekday, Shift $shift): bool
    {
        return $this->recurringLevel($employee, $weekday, $shift) === RecurringAvailability::LEVELS[0];
    }

    private function recurringLevel(Employee $employee, int $weekday, Shift $shift): ?string
    {
        return RecurringAvailability::query()
            ->where('employee_id', $employee->id)
            ->where('weekday', $weekday)
            ->where('shift_id', $shift->id)
            ->value('level');
    }

    /** Any other same-date assignment for this employee whose shift's clock range intersects this one's. */
    public function hasOverlap(Employee $employee, Carbon $date, Shift $shift, ?int $excludeAssignmentId = null): bool
    {
        // Raw (unaccessed) values, so the comparison matches the `time`
        // column's own stored format exactly, not the "H:i" the model's
        // accessor trims it to for display.
        $start = $shift->getRawOriginal('start_time');
        $end = $shift->getRawOriginal('end_time');

        return ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->when($excludeAssignmentId, fn ($q) => $q->whereKeyNot($excludeAssignmentId))
            ->whereHas('shift', fn ($q) => $q
                ->where('start_time', '<', $end)
                ->where('end_time', '>', $start))
            ->exists();
    }
}
