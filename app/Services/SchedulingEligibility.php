<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PlanningRule;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Services\Planning\PlanningCycle;
use Carbon\Carbon;

/**
 * Shared holiday/unavailable/overlap checks, used both to filter the
 * eligible-employee list and to re-validate a manual assignment write.
 */
class SchedulingEligibility
{
    public function assignmentBlockReason(Employee $employee, Workcenter $workcenter, Shift $shift, Carbon $date): ?string
    {
        $assignments = ShiftAssignment::query()
            ->where('workcenter_id', $workcenter->id)
            ->where('shift_id', $shift->id)
            ->whereDate('date', $date);

        if ((clone $assignments)->where('employee_id', $employee->id)->exists()) {
            return 'duplicate';
        }

        if (! $employee->confirmed) {
            return 'unconfirmed';
        }

        if ($assignments->count() >= $workcenter->spotsFor($shift, $date)) {
            return 'cell_full';
        }

        if ($this->isShiftUnavailableForWorkcenter($employee, $workcenter, $shift)) {
            return 'shift_hidden';
        }

        if ($this->isOnHoliday($employee, $date)) {
            return 'holiday';
        }

        if ($this->isUnavailable($employee, $date->isoWeekday(), $shift)) {
            return 'unavailable';
        }

        if ($this->isWorkcenterIneligible($employee, $workcenter)) {
            return 'workcenter_ineligible';
        }

        if ($this->hasOverlap($employee, $date, $shift)) {
            return 'overlap';
        }

        return $this->hardCapViolation($employee, $shift, $date);
    }

    public function isOnHoliday(Employee $employee, Carbon $date): bool
    {
        return $employee->holidays()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    public function isUnavailable(Employee $employee, int $weekday, Shift $shift): bool
    {
        return ! in_array($this->recurringLevel($employee, $weekday, $shift), ['available', 'not_preferred'], true);
    }

    public function isShiftUnavailableForWorkcenter(Employee $employee, Workcenter $workcenter, Shift $shift): bool
    {
        if ($shift->visible_by_default) {
            return false;
        }

        return ! $employee->workcenters()
            ->where('workcenters.id', $workcenter->id)
            ->wherePivot('mode', 'hard')
            ->whereHas('shifts', fn ($q) => $q->where('shifts.id', $shift->id))
            ->exists();
    }

    public function isNotPreferred(Employee $employee, int $weekday, Shift $shift): bool
    {
        return $this->recurringLevel($employee, $weekday, $shift) === RecurringAvailability::LEVELS[0];
    }

    /** True only when the employee holds at least one hard row and this workcenter is not one of them. */
    public function isWorkcenterIneligible(Employee $employee, Workcenter $workcenter): bool
    {
        $modes = $employee->workcenters()->pluck('employee_workcenter.mode', 'workcenters.id');

        if (! $modes->contains('hard')) {
            return false;
        }

        return ($modes[$workcenter->id] ?? null) !== 'hard';
    }

    public function isWorkcenterNotPreferred(Employee $employee, Workcenter $workcenter): bool
    {
        return $employee->workcenters()
            ->where('workcenters.id', $workcenter->id)
            ->wherePivot('mode', 'soft')
            ->exists();
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

    /** Return the first hard planning cap exceeded by a proposed assignment, or null. */
    public function hardCapViolation(Employee $employee, Shift $shift, Carbon $date): ?string
    {
        $rules = PlanningRule::query()
            ->whereIn('type', ['max_shifts_per_day', 'max_hours_per_week'])
            ->where('mode', 'hard')
            ->get()
            ->keyBy('type');

        $dailyRule = $rules->get('max_shifts_per_day');
        if ($dailyRule !== null && ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->count() + 1 > (int) ($dailyRule->config['value'] ?? 0)) {
            return 'max_shifts_per_day';
        }

        $hoursRule = $rules->get('max_hours_per_week');
        $cycleStart = PlanningCycle::containing($date);
        if ($hoursRule !== null && $cycleStart !== null) {
            $cycleEnd = $cycleStart->copy()->addDays(13);
            $assignments = ShiftAssignment::query()
                ->where('employee_id', $employee->id)
                ->with('shift');
            $shiftHours = $shift->capHours();
            $cycleHours = (clone $assignments)
                ->whereBetween('date', [$cycleStart->toDateString(), $cycleEnd->toDateString()])
                ->get()
                ->sum(fn (ShiftAssignment $assignment): float => $assignment->shift->capHours());

            if ($cycleHours + $shiftHours > $employee->weekly_hours * 2) {
                return 'max_hours_per_week';
            }

            $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
            $weekEnd = $date->copy()->endOfWeek(Carbon::SUNDAY);
            $weekHours = (clone $assignments)
                ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->get()
                ->sum(fn (ShiftAssignment $assignment): float => $assignment->shift->capHours());

            if ($weekHours + $shiftHours > $employee->weekly_hours + 4) {
                return 'max_hours_per_week_distribution';
            }
        }

        return null;
    }
}
