<?php

namespace App\Services\Planning;

use App\Models\Shift;

/**
 * Mutable working state for one planner run: every assignment currently
 * "in effect" for the cycle, seeded from the locked assignments and then
 * grown (and, from the hill-climbing phase on, rearranged) as the planner
 * works. Shared by {@see GreedyConstructor} and the optimizer so both
 * reason about overlap/hour/day-count totals the same way.
 */
final class PlanAssignmentSet
{
    /** @var array<string, array{employee_id: int, workcenter_id: int, shift_id: int, date: string, locked: bool}> */
    private array $byKey = [];

    /** @var array<int, string[]> employee_id => list of keys, for fast per-employee lookups. */
    private array $keysByEmployee = [];

    /**
     * Cells ("workcenter:shift:date") that had at least one eligible
     * candidate at some point across construction and optimization —
     * spans both phases so a cell that had a candidate during construction
     * but lost it to a cap, or one hill-climbing considered filling but
     * a competing move ultimately won out, is still told apart from a
     * cell that never had anyone eligible at all, once the run settles.
     *
     * @var array<string, true>
     */
    private array $everHadCandidate = [];

    /**
     * @param  array<int, array{id: int, start_time: string, end_time: string}>  $shifts
     */
    public function __construct(private readonly array $shifts) {}

    public static function key(int $employeeId, int $workcenterId, int $shiftId, string $date): string
    {
        return "{$employeeId}:{$workcenterId}:{$shiftId}:{$date}";
    }

    public function add(int $employeeId, int $workcenterId, int $shiftId, string $date, bool $locked = false): void
    {
        $key = self::key($employeeId, $workcenterId, $shiftId, $date);
        $this->byKey[$key] = [
            'employee_id' => $employeeId,
            'workcenter_id' => $workcenterId,
            'shift_id' => $shiftId,
            'date' => $date,
            'locked' => $locked,
        ];
        $this->keysByEmployee[$employeeId][$key] = $key;
    }

    public function remove(int $employeeId, int $workcenterId, int $shiftId, string $date): void
    {
        $key = self::key($employeeId, $workcenterId, $shiftId, $date);
        unset($this->byKey[$key], $this->keysByEmployee[$employeeId][$key]);
    }

    /** @return array<int, array{employee_id: int, workcenter_id: int, shift_id: int, date: string, locked: bool}> */
    public function all(): array
    {
        return array_values($this->byKey);
    }

    /** @return array<int, array{workcenter_id: int, shift_id: int, date: string}> Only the planner's own (non-locked) assignments. */
    public function movable(): array
    {
        return array_values(array_map(
            fn (array $a) => ['employee_id' => $a['employee_id'], 'workcenter_id' => $a['workcenter_id'], 'shift_id' => $a['shift_id'], 'date' => $a['date']],
            array_filter($this->byKey, fn (array $a) => ! $a['locked']),
        ));
    }

    public function noteCandidateSeen(int $workcenterId, int $shiftId, string $date): void
    {
        $this->everHadCandidate["{$workcenterId}:{$shiftId}:{$date}"] = true;
    }

    public function hadCandidate(int $workcenterId, int $shiftId, string $date): bool
    {
        return $this->everHadCandidate["{$workcenterId}:{$shiftId}:{$date}"] ?? false;
    }

    public function countForCell(int $workcenterId, int $shiftId, string $date): int
    {
        return count(array_filter(
            $this->byKey,
            fn (array $a) => $a['workcenter_id'] === $workcenterId && $a['shift_id'] === $shiftId && $a['date'] === $date,
        ));
    }

    public function isAssigned(int $employeeId, int $workcenterId, int $shiftId, string $date): bool
    {
        return isset($this->byKey[self::key($employeeId, $workcenterId, $shiftId, $date)]);
    }

    /** True when $employeeId holds $shiftId (any workcenter) on $date — for alternating-pair checks. */
    public function hasShiftOnDate(int $employeeId, int $shiftId, string $date): bool
    {
        foreach ($this->forEmployee($employeeId) as $a) {
            if ($a['date'] === $date && $a['shift_id'] === $shiftId) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array{employee_id: int, workcenter_id: int, shift_id: int, date: string, locked: bool}> */
    public function assignmentsFor(int $employeeId): array
    {
        return $this->forEmployee($employeeId);
    }

    public function countOnDate(int $employeeId, string $date): int
    {
        return count(array_filter(
            $this->forEmployee($employeeId),
            fn (array $a) => $a['date'] === $date,
        ));
    }

    public function totalHours(int $employeeId): float
    {
        $hours = 0.0;
        foreach ($this->forEmployee($employeeId) as $a) {
            $hours += $this->shiftDurationHours($a['shift_id']);
        }

        return $hours;
    }

    public function hasOverlap(int $employeeId, string $date, int $shiftId): bool
    {
        $shift = $this->shift($shiftId);
        if (! $shift) {
            return false;
        }

        foreach ($this->forEmployee($employeeId) as $a) {
            if ($a['date'] !== $date) {
                continue;
            }
            $other = $this->shift($a['shift_id']);
            if (! $other) {
                continue;
            }
            // Symmetric interval-overlap test, matching SchedulingEligibility::hasOverlap
            // exactly (raw start/end string comparison — a known limitation for shifts
            // that wrap past midnight, unchanged here to stay consistent with manual
            // assignment's existing behavior).
            if ($shift['start_time'] < $other['end_time'] && $other['start_time'] < $shift['end_time']) {
                return true;
            }
        }

        return false;
    }

    public function shiftDurationHours(int $shiftId): float
    {
        $shift = $this->shift($shiftId);
        if (! $shift) {
            return 0.0;
        }

        return Shift::durationHoursBetween($shift['start_time'], $shift['end_time']);
    }

    /** @return array<int, array{employee_id: int, workcenter_id: int, shift_id: int, date: string, locked: bool}> */
    private function forEmployee(int $employeeId): array
    {
        return array_map(
            fn (string $key) => $this->byKey[$key],
            array_values($this->keysByEmployee[$employeeId] ?? []),
        );
    }

    private function shift(int $shiftId): ?array
    {
        foreach ($this->shifts as $shift) {
            if ($shift['id'] === $shiftId) {
                return $shift;
            }
        }

        return null;
    }
}
