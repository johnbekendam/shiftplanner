<?php

namespace App\Services\Planning;

use Carbon\Carbon;

/**
 * Hard-eligibility rules, precomputed once per run from a {@see PlanProblem}
 * into fast lookups. Shared by the greedy construction and (from the
 * hill-climbing phase on) the optimizer's move-legality checks, so both
 * phases agree on what "allowed" means.
 */
final class PlanEligibility
{
    /** @var array<int, array<int, array{start: string, end: string}>> employee_id => holiday ranges */
    private array $holidays = [];

    /** @var array<int, array<string, string>> employee_id => "weekday:shift_id" => level */
    private array $availability = [];

    /** @var array<int, array<int, true>> employee_id => workcenter_id set */
    private array $workcenters = [];

    /** @var array<int, array<int, true>> employee_id => competence_id set */
    private array $competences = [];

    /** @var array<int, ?int> employee_id => business_line_id */
    private array $businessLines = [];

    /** @var array<int, int[]> workcenter_id => required competence_ids (hard rules only) */
    private array $hardCompetenceRequired = [];

    /** @var array<int, int> workcenter_id => required business_line_id (hard rule only) */
    private array $hardBusinessLinePreference = [];

    private ?int $hardMaxShiftsPerDay = null;

    private bool $hardMaxHoursPerWeek = false;

    private bool $hardNotPreferredShift = false;

    /** @var array<int, int> shift_id => the other shift of its alternating pair */
    private array $pairedShift = [];

    public function __construct(private readonly PlanProblem $problem)
    {
        foreach ($problem->employees as $employee) {
            $id = $employee['id'];
            $this->holidays[$id] = $employee['holidays'];
            $this->businessLines[$id] = $employee['business_line_id'];

            foreach ($employee['recurring_availability'] as $row) {
                $this->availability[$id]["{$row['weekday']}:{$row['shift_id']}"] = $row['level'];
            }

            foreach ($employee['workcenter_ids'] as $workcenterId) {
                $this->workcenters[$id][$workcenterId] = true;
            }

            foreach ($employee['competences'] as $competenceId) {
                $this->competences[$id][$competenceId] = true;
            }
        }

        foreach ($problem->rules as $rule) {
            // Always hard, whatever mode a row stores.
            if ($rule['type'] === 'alternating_shift_pair') {
                $first = $rule['config']['first_shift_id'];
                $second = $rule['config']['second_shift_id'];
                $this->pairedShift[$first] = $second;
                $this->pairedShift[$second] = $first;

                continue;
            }

            if ($rule['mode'] !== 'hard') {
                continue;
            }

            match ($rule['type']) {
                'competence_required' => $this->hardCompetenceRequired[$rule['config']['workcenter_id']][] = $rule['config']['competence_id'],
                'business_line_preference' => $this->hardBusinessLinePreference[$rule['config']['workcenter_id']] = $rule['config']['business_line_id'],
                'max_shifts_per_day' => $this->hardMaxShiftsPerDay = $rule['config']['value'],
                'max_hours_per_week' => $this->hardMaxHoursPerWeek = true,
                'not_preferred_shift' => $this->hardNotPreferredShift = true,
                default => null,
            };
        }
    }

    /** True if hard rules allow this employee on this cell, given the current working state. */
    public function isEligible(array $employee, int $workcenterId, int $shiftId, string $date, PlanAssignmentSet $current): bool
    {
        $id = $employee['id'];

        return ! $this->isOnHoliday($id, $date)
            && $this->isAssignableLevel($this->recurringLevel($id, $date, $shiftId))
            && ! $this->isWorkcenterIneligible($id, $workcenterId)
            && $this->holdsRequiredCompetences($id, $workcenterId)
            && $this->matchesRequiredBusinessLine($id, $workcenterId)
            && ! $current->hasOverlap($id, $date, $shiftId)
            && $this->withinMaxShiftsPerDay($current, $id, $date)
            && $this->withinMaxHoursPerWeek($current, $employee, $shiftId, $date)
            && ! $this->combinesPairInWeek($current, $id, $shiftId, $date);
    }

    /** A hard not_preferred_shift rule closes not-preferred cells like unavailable ones. */
    private function isAssignableLevel(string $level): bool
    {
        return $level === 'available' || ($level === 'not_preferred' && ! $this->hardNotPreferredShift);
    }

    private function isOnHoliday(int $employeeId, string $date): bool
    {
        foreach ($this->holidays[$employeeId] ?? [] as $range) {
            if ($date >= $range['start'] && $date <= $range['end']) {
                return true;
            }
        }

        return false;
    }

    /** Matches SchedulingEligibility: a missing cell is unavailable, not available. */
    private function recurringLevel(int $employeeId, string $date, int $shiftId): ?string
    {
        $weekday = Carbon::parse($date)->isoWeekday();
        $level = $this->availability[$employeeId]["{$weekday}:{$shiftId}"] ?? null;

        return $level ?? 'unavailable';
    }

    /** Matches SchedulingEligibility::isWorkcenterIneligible exactly. */
    private function isWorkcenterIneligible(int $employeeId, int $workcenterId): bool
    {
        return ! isset($this->workcenters[$employeeId][$workcenterId]);
    }

    private function holdsRequiredCompetences(int $employeeId, int $workcenterId): bool
    {
        foreach ($this->hardCompetenceRequired[$workcenterId] ?? [] as $competenceId) {
            if (! isset($this->competences[$employeeId][$competenceId])) {
                return false;
            }
        }

        return true;
    }

    private function matchesRequiredBusinessLine(int $employeeId, int $workcenterId): bool
    {
        if (! isset($this->hardBusinessLinePreference[$workcenterId])) {
            return true;
        }

        return $this->businessLines[$employeeId] === $this->hardBusinessLinePreference[$workcenterId];
    }

    /** An employee never holds both shifts of an alternating pair in the same week. */
    private function combinesPairInWeek(PlanAssignmentSet $current, int $employeeId, int $shiftId, string $date): bool
    {
        if (! isset($this->pairedShift[$shiftId])) {
            return false;
        }

        return $current->hasShiftInWeek($employeeId, $this->pairedShift[$shiftId], $date);
    }

    private function withinMaxShiftsPerDay(PlanAssignmentSet $current, int $employeeId, string $date): bool
    {
        if ($this->hardMaxShiftsPerDay === null) {
            return true;
        }

        return $current->countOnDate($employeeId, $date) + 1 <= $this->hardMaxShiftsPerDay;
    }

    private function withinMaxHoursPerWeek(PlanAssignmentSet $current, array $employee, int $shiftId, string $date): bool
    {
        if (! $this->hardMaxHoursPerWeek) {
            return true;
        }

        $shiftHours = $current->shiftCapHours($shiftId);
        $cycleWithinCap = $current->totalCapHours($employee['id']) + $shiftHours <= $employee['weekly_hours'] * 2;
        $weekWithinCap = $current->totalCapHoursForWeek($employee['id'], $date) + $shiftHours <= $employee['weekly_hours'] + 4;

        return $cycleWithinCap && $weekWithinCap;
    }
}
