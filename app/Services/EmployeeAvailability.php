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

    /** Blocking statuses that are their own block reason; any other one is "unavailable". */
    private const OWN_REASON = ['not_started', 'holiday'];

    /**
     * @param  array<int, array{start: string, end: string}>  $holidays  inclusive Y-m-d ranges
     * @param  array<string, string>  $weekly  "isoWeekday:shift_id" => level
     * @param  array<string, true>  $dayBlocks  Y-m-d => true, whole-day blocks
     * @param  array<string, string>  $overrides  "Y-m-d:shift_id" => level
     */
    public function __construct(
        private readonly array $holidays,
        private readonly array $weekly,
        private readonly ?string $availableFrom = null,
        private readonly array $dayBlocks = [],
        private readonly array $overrides = [],
    ) {}

    public static function fromEmployee(Employee $employee): self
    {
        [$dayBlocks, $overrides] = self::splitOverrides($employee->availabilityOverrides->map->toPayload()->all());

        return new self(
            holidays: $employee->holidays
                ->map(fn ($h) => ['start' => $h->start_date->toDateString(), 'end' => $h->end_date->toDateString()])
                ->all(),
            weekly: $employee->recurringAvailabilities
                ->mapWithKeys(fn ($r) => ["{$r->weekday}:{$r->shift_id}" => $r->level])
                ->all(),
            availableFrom: $employee->available_from?->toDateString(),
            dayBlocks: $dayBlocks,
            overrides: $overrides,
        );
    }

    /** @param  array{holidays: array, recurring_availability: array, available_from?: ?string, availability_overrides?: array}  $employee  a PlanProblem employee */
    public static function fromPlanEmployee(array $employee): self
    {
        [$dayBlocks, $overrides] = self::splitOverrides($employee['availability_overrides'] ?? []);
        $weekly = [];
        foreach ($employee['recurring_availability'] as $row) {
            $weekly["{$row['weekday']}:{$row['shift_id']}"] = $row['level'];
        }

        return new self(
            holidays: $employee['holidays'],
            weekly: $weekly,
            availableFrom: $employee['available_from'] ?? null,
            dayBlocks: $dayBlocks,
            overrides: $overrides,
        );
    }

    /**
     * @param  array<int, array{date: string, shift_id: ?int, level: string}>  $rows
     * @return array{0: array<string, true>, 1: array<string, string>} whole-day blocks, shift overrides
     */
    private static function splitOverrides(array $rows): array
    {
        $dayBlocks = [];
        $overrides = [];
        foreach ($rows as $row) {
            if ($row['shift_id'] === null) {
                $dayBlocks[$row['date']] = true;
            } else {
                $overrides["{$row['date']}:{$row['shift_id']}"] = $row['level'];
            }
        }

        return [$dayBlocks, $overrides];
    }

    public static function isAssignable(string $status): bool
    {
        return in_array($status, self::ASSIGNABLE, true);
    }

    /** The block reason (and verification code) for a status that is not assignable. */
    public static function blockReason(string $status): string
    {
        return in_array($status, self::OWN_REASON, true) ? $status : 'unavailable';
    }

    /**
     * The first match wins: before the start date, holiday, whole-day
     * block, shift override, then the weekly default. A missing weekly row
     * is unavailable, not available.
     */
    public function status(string $date, int $shiftId): string
    {
        if ($this->availableFrom !== null && $date < $this->availableFrom) {
            return 'not_started';
        }

        if ($this->isOnHoliday($date)) {
            return 'holiday';
        }

        if (isset($this->dayBlocks[$date])) {
            return 'unavailable';
        }

        if (isset($this->overrides["{$date}:{$shiftId}"])) {
            return $this->overrides["{$date}:{$shiftId}"];
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
