<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;

/**
 * The one answer to "is this employee available for this shift on this
 * date". Built from loaded relations (manual planning, verification) or
 * from a PlanProblem employee array (the planner), so every caller
 * resolves availability in the same order.
 */
final class EmployeeAvailability
{
    /** Statuses a manual assignment or the planner may use. */
    private const ASSIGNABLE = ['available', 'not_preferred'];

    /**
     * @param  array<int, array{start: string, end: string}>  $holidays  inclusive Y-m-d ranges
     * @param  array<string, string>  $weekly  "isoWeekday:shift_id" => level
     */
    public function __construct(
        private readonly array $holidays,
        private readonly array $weekly,
    ) {}

    public static function fromEmployee(Employee $employee): self
    {
        return new self(
            holidays: $employee->holidays
                ->map(fn ($h) => ['start' => $h->start_date->toDateString(), 'end' => $h->end_date->toDateString()])
                ->all(),
            weekly: $employee->recurringAvailabilities
                ->mapWithKeys(fn ($r) => ["{$r->weekday}:{$r->shift_id}" => $r->level])
                ->all(),
        );
    }

    /** @param  array{holidays: array, recurring_availability: array}  $employee  a PlanProblem employee */
    public static function fromPlanEmployee(array $employee): self
    {
        $weekly = [];
        foreach ($employee['recurring_availability'] as $row) {
            $weekly["{$row['weekday']}:{$row['shift_id']}"] = $row['level'];
        }

        return new self(holidays: $employee['holidays'], weekly: $weekly);
    }

    public static function isAssignable(string $status): bool
    {
        return in_array($status, self::ASSIGNABLE, true);
    }

    /**
     * The first match wins: holiday, then the weekly default. A missing
     * weekly row is unavailable, not available.
     */
    public function status(string $date, int $shiftId): string
    {
        if ($this->isOnHoliday($date)) {
            return 'holiday';
        }

        $weekday = Carbon::parse($date)->isoWeekday();

        return $this->weekly["{$weekday}:{$shiftId}"] ?? 'unavailable';
    }

    private function isOnHoliday(string $date): bool
    {
        foreach ($this->holidays as $range) {
            if ($date >= $range['start'] && $date <= $range['end']) {
                return true;
            }
        }

        return false;
    }
}
