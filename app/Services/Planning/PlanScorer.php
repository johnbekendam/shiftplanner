<?php

namespace App\Services\Planning;

use Carbon\Carbon;

/**
 * Collapses the three lexicographic tiers `planning-rules` defined
 * (coverage, then workload fairness, then severity-weighted soft
 * preferences) into one scalar, weighted so a lower tier can never
 * outweigh a higher one (`W1 ≫ W2 ≫ W3`). Lower is better; hill-climbing
 * only ever accepts a move that strictly reduces this score.
 */
final class PlanScorer
{
    private const TIER1_WEIGHT = 1_000_000.0;

    private const TIER2_WEIGHT = 1_000.0;

    /** @var array<int, array> employee_id => employee row, for O(1) lookups in hot loops. */
    private array $employeesById;

    public function __construct(
        private readonly PlanProblem $problem,
        private readonly PlanSoftRules $softRules,
    ) {
        $this->employeesById = [];
        foreach ($problem->employees as $employee) {
            $this->employeesById[$employee['id']] = $employee;
        }
    }

    public function score(PlanAssignmentSet $assignments): float
    {
        return $this->coverageShortfall($assignments) * self::TIER1_WEIGHT
            + $this->maxWorkloadHours($assignments) * self::TIER2_WEIGHT
            + $this->softCost($assignments);
    }

    private function coverageShortfall(PlanAssignmentSet $assignments): int
    {
        $shortfall = 0;
        foreach ($this->problem->spots as $spot) {
            $shortfall += max(0, $spot['spots'] - $assignments->countForCell($spot['workcenter_id'], $spot['shift_id'], $spot['date']));
        }

        return $shortfall;
    }

    private function maxWorkloadHours(PlanAssignmentSet $assignments): float
    {
        if (! $this->softRules->equalWorkload || $this->problem->employees === []) {
            return 0.0;
        }

        $max = 0.0;
        foreach ($this->problem->employees as $employee) {
            $max = max($max, $assignments->totalHours($employee['id']));
        }

        return $max;
    }

    private function softCost(PlanAssignmentSet $assignments): float
    {
        $cost = 0.0;

        foreach ($assignments->all() as $a) {
            $cost += $this->notPreferredCost($a);
            $cost += $this->competenceCost($a);
            $cost += $this->businessLineCost($a);
        }

        $cost += $this->alternatingPairCost($assignments);

        foreach ($this->problem->employees as $employee) {
            $cost += $this->maxShiftsPerDayCost($assignments, $employee);
            $cost += $this->maxHoursPerWeekCost($assignments, $employee);
        }

        return $cost;
    }

    private function notPreferredCost(array $a): float
    {
        if ($this->softRules->notPreferredShift === null) {
            return 0.0;
        }

        $employee = $this->employeesById[$a['employee_id']] ?? null;
        if (! $employee) {
            return 0.0;
        }

        $weekday = Carbon::parse($a['date'])->isoWeekday();
        foreach ($employee['recurring_availability'] as $row) {
            if ($row['weekday'] === $weekday && $row['shift_id'] === $a['shift_id'] && $row['level'] === 'not_preferred') {
                return (float) $this->softRules->notPreferredShift['severity'];
            }
        }

        return 0.0;
    }

    private function competenceCost(array $a): float
    {
        $cost = 0.0;
        $employee = $this->employeesById[$a['employee_id']] ?? null;
        if (! $employee) {
            return 0.0;
        }

        foreach ($this->softRules->competenceRequired as $rule) {
            if ($rule['workcenter_id'] === $a['workcenter_id'] && ! in_array($rule['competence_id'], $employee['competences'], true)) {
                $cost += $rule['severity'];
            }
        }

        return $cost;
    }

    private function businessLineCost(array $a): float
    {
        $cost = 0.0;
        $employee = $this->employeesById[$a['employee_id']] ?? null;
        if (! $employee) {
            return 0.0;
        }

        foreach ($this->softRules->businessLinePreference as $rule) {
            if ($rule['workcenter_id'] === $a['workcenter_id'] && $employee['business_line_id'] !== $rule['business_line_id']) {
                $cost += $rule['severity'];
            }
        }

        return $cost;
    }

    private function alternatingPairCost(PlanAssignmentSet $assignments): float
    {
        if ($this->softRules->alternatingPairs === []) {
            return 0.0;
        }

        $cost = 0.0;
        foreach ($this->problem->employees as $employee) {
            foreach ($this->softRules->alternatingPairs as $pair) {
                $cost += $this->alternatingPairCostFor($assignments, $employee['id'], $pair);
            }
        }

        return $cost;
    }

    private function alternatingPairCostFor(PlanAssignmentSet $assignments, int $employeeId, array $pair): float
    {
        $cost = 0.0;

        for ($offset = 0; $offset < 14; $offset++) {
            $date = $this->problem->cycleStart->copy()->addDays($offset);
            $earlierDate = $date->copy()->subDays(7)->toDateString();

            $earlierHasFirst = $this->hasShiftOn($assignments, $employeeId, $pair['first_shift_id'], $earlierDate);
            $earlierHasSecond = $this->hasShiftOn($assignments, $employeeId, $pair['second_shift_id'], $earlierDate);

            if ($earlierHasFirst === $earlierHasSecond) {
                continue; // 0 or 2 members that day: no preference
            }

            $repeated = $earlierHasFirst ? $pair['first_shift_id'] : $pair['second_shift_id'];
            $opposite = $earlierHasFirst ? $pair['second_shift_id'] : $pair['first_shift_id'];
            $dateStr = $date->toDateString();

            if ($assignments->hasShiftOnDate($employeeId, $repeated, $dateStr) && ! $assignments->hasShiftOnDate($employeeId, $opposite, $dateStr)) {
                $cost += $pair['severity'];
            }
        }

        return $cost;
    }

    /** $date may fall before the cycle (previous_week_assignments, read-only) or within it (the working assignment set). */
    private function hasShiftOn(PlanAssignmentSet $assignments, int $employeeId, int $shiftId, string $date): bool
    {
        if ($date < $this->problem->cycleStart->toDateString()) {
            foreach ($this->problem->previousWeekAssignments as $a) {
                if ($a['employee_id'] === $employeeId && $a['shift_id'] === $shiftId && $a['date'] === $date) {
                    return true;
                }
            }

            return false;
        }

        return $assignments->hasShiftOnDate($employeeId, $shiftId, $date);
    }

    private function maxShiftsPerDayCost(PlanAssignmentSet $assignments, array $employee): float
    {
        if ($this->softRules->maxShiftsPerDay === null) {
            return 0.0;
        }

        $cost = 0.0;
        $cap = $this->softRules->maxShiftsPerDay['value'];
        for ($offset = 0; $offset < 14; $offset++) {
            $date = $this->problem->cycleStart->copy()->addDays($offset)->toDateString();
            $over = $assignments->countOnDate($employee['id'], $date) - $cap;
            if ($over > 0) {
                $cost += $over * $this->softRules->maxShiftsPerDay['severity'];
            }
        }

        return $cost;
    }

    private function maxHoursPerWeekCost(PlanAssignmentSet $assignments, array $employee): float
    {
        if ($this->softRules->maxHoursPerWeek === null) {
            return 0.0;
        }

        $over = $assignments->totalCapHours($employee['id']) - ($employee['weekly_hours'] * 2);

        return $over > 0 ? $over * $this->softRules->maxHoursPerWeek['severity'] : 0.0;
    }
}
