<?php

namespace App\Services\Planning;

/**
 * Local search against {@see PlanScorer}'s single scalar: repeatedly tries
 * moves and keeps any that strictly reduce the score, stopping at a local
 * optimum or the iteration/time budget. Move generation is exhaustive over
 * every movable assignment each pass (not a random sample), which already
 * guarantees a move that unloads the current maximum-hours employee is in
 * the candidate set whenever one exists — no separate "targeted" code path
 * is needed for that on top of it; sampling would need one, full
 * enumeration doesn't (see spec.md's Key decisions).
 */
final class HillClimbOptimizer
{
    private const MAX_ITERATIONS = 2000;

    private const MAX_SECONDS = 10.0;

    /** Fixed, not random — reproducible test runs. */
    private const SEED = 20260918;

    /** @var array<int, array> employee_id => employee row. */
    private array $employeesById;

    public function __construct(
        private readonly PlanProblem $problem,
        private readonly PlanEligibility $eligibility,
        private readonly PlanScorer $scorer,
    ) {
        $this->employeesById = [];
        foreach ($problem->employees as $employee) {
            $this->employeesById[$employee['id']] = $employee;
        }
    }

    public function optimize(PlanAssignmentSet $assignments): void
    {
        mt_srand(self::SEED);
        $deadline = microtime(true) + self::MAX_SECONDS;

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++) {
            if (microtime(true) > $deadline) {
                break;
            }

            $currentScore = $this->scorer->score($assignments);
            $moves = $this->candidateMoves($assignments);
            shuffle($moves);

            $improved = false;
            foreach ($moves as $move) {
                $this->apply($move, $assignments);
                if ($this->scorer->score($assignments) < $currentScore) {
                    $improved = true;
                    break;
                }
                $this->revert($move, $assignments);
            }

            if (! $improved) {
                break; // local optimum reached
            }
        }
    }

    private function candidateMoves(PlanAssignmentSet $assignments): array
    {
        return [
            ...$this->fillMoves($assignments),
            ...$this->relocateMoves($assignments),
            ...$this->substituteMoves($assignments),
            ...$this->swapMoves($assignments),
        ];
    }

    /** @return array<int, array> */
    private function fillMoves(PlanAssignmentSet $assignments): array
    {
        $moves = [];
        foreach ($this->problem->spots as $spot) {
            if ($assignments->countForCell($spot['workcenter_id'], $spot['shift_id'], $spot['date']) >= $spot['spots']) {
                continue;
            }
            foreach ($this->problem->employees as $employee) {
                if ($assignments->isAssigned($employee['id'], $spot['workcenter_id'], $spot['shift_id'], $spot['date'])) {
                    continue;
                }
                if ($this->eligibility->isEligible($employee, $spot['workcenter_id'], $spot['shift_id'], $spot['date'], $assignments)) {
                    $assignments->noteCandidateSeen($spot['workcenter_id'], $spot['shift_id'], $spot['date']);
                    $moves[] = [
                        'type' => 'fill',
                        'employee_id' => $employee['id'],
                        'workcenter_id' => $spot['workcenter_id'],
                        'shift_id' => $spot['shift_id'],
                        'date' => $spot['date'],
                    ];
                }
            }
        }

        return $moves;
    }

    /** @return array<int, array> */
    private function relocateMoves(PlanAssignmentSet $assignments): array
    {
        $moves = [];
        foreach ($assignments->movable() as $current) {
            $employee = $this->employeesById[$current['employee_id']] ?? null;
            if (! $employee) {
                continue;
            }

            $assignments->remove($current['employee_id'], $current['workcenter_id'], $current['shift_id'], $current['date']);

            foreach ($this->problem->spots as $spot) {
                $sameCell = $spot['workcenter_id'] === $current['workcenter_id']
                    && $spot['shift_id'] === $current['shift_id']
                    && $spot['date'] === $current['date'];
                if ($sameCell || $assignments->countForCell($spot['workcenter_id'], $spot['shift_id'], $spot['date']) >= $spot['spots']) {
                    continue;
                }
                if ($assignments->isAssigned($employee['id'], $spot['workcenter_id'], $spot['shift_id'], $spot['date'])) {
                    continue;
                }
                if ($this->eligibility->isEligible($employee, $spot['workcenter_id'], $spot['shift_id'], $spot['date'], $assignments)) {
                    $assignments->noteCandidateSeen($spot['workcenter_id'], $spot['shift_id'], $spot['date']);
                    $moves[] = [
                        'type' => 'relocate',
                        'employee_id' => $employee['id'],
                        'from' => ['workcenter_id' => $current['workcenter_id'], 'shift_id' => $current['shift_id'], 'date' => $current['date']],
                        'to' => ['workcenter_id' => $spot['workcenter_id'], 'shift_id' => $spot['shift_id'], 'date' => $spot['date']],
                    ];
                }
            }

            $assignments->add($employee['id'], $current['workcenter_id'], $current['shift_id'], $current['date']);
        }

        return $moves;
    }

    /**
     * Replaces the occupant of an already-full cell with a different eligible
     * employee — net effect: the cell stays filled (tier 1 unaffected), the
     * incumbent loses the assignment, the replacement gains it, with no
     * compensating assignment for either. Needed alongside relocate/swap:
     * neither can rebalance a fully-staffed cycle where one employee holds
     * nothing at all — fill needs an open cell, swap needs both sides to
     * already hold something to trade.
     *
     * @return array<int, array>
     */
    private function substituteMoves(PlanAssignmentSet $assignments): array
    {
        $moves = [];
        foreach ($assignments->movable() as $current) {
            foreach ($this->problem->employees as $employee) {
                if ($employee['id'] === $current['employee_id']) {
                    continue;
                }
                if ($assignments->isAssigned($employee['id'], $current['workcenter_id'], $current['shift_id'], $current['date'])) {
                    continue;
                }

                $assignments->remove($current['employee_id'], $current['workcenter_id'], $current['shift_id'], $current['date']);
                $eligible = $this->eligibility->isEligible($employee, $current['workcenter_id'], $current['shift_id'], $current['date'], $assignments);
                $assignments->add($current['employee_id'], $current['workcenter_id'], $current['shift_id'], $current['date']);

                if ($eligible) {
                    $assignments->noteCandidateSeen($current['workcenter_id'], $current['shift_id'], $current['date']);
                    $moves[] = [
                        'type' => 'substitute',
                        'cell' => ['workcenter_id' => $current['workcenter_id'], 'shift_id' => $current['shift_id'], 'date' => $current['date']],
                        'from_employee_id' => $current['employee_id'],
                        'to_employee_id' => $employee['id'],
                    ];
                }
            }
        }

        return $moves;
    }

    /** @return array<int, array> */
    private function swapMoves(PlanAssignmentSet $assignments): array
    {
        $moves = [];
        $movable = $assignments->movable();
        $count = count($movable);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $movable[$i];
                $b = $movable[$j];
                if ($a['employee_id'] === $b['employee_id']) {
                    continue; // swapping someone's own two shifts is a no-op
                }
                if ($a['workcenter_id'] === $b['workcenter_id'] && $a['shift_id'] === $b['shift_id'] && $a['date'] === $b['date']) {
                    continue; // same cell — nothing to swap
                }

                $employeeA = $this->employeesById[$a['employee_id']] ?? null;
                $employeeB = $this->employeesById[$b['employee_id']] ?? null;
                if (! $employeeA || ! $employeeB) {
                    continue;
                }

                $assignments->remove($a['employee_id'], $a['workcenter_id'], $a['shift_id'], $a['date']);
                $assignments->remove($b['employee_id'], $b['workcenter_id'], $b['shift_id'], $b['date']);

                $aCanTakeB = ! $assignments->isAssigned($employeeA['id'], $b['workcenter_id'], $b['shift_id'], $b['date'])
                    && $this->eligibility->isEligible($employeeA, $b['workcenter_id'], $b['shift_id'], $b['date'], $assignments);
                $bCanTakeA = ! $assignments->isAssigned($employeeB['id'], $a['workcenter_id'], $a['shift_id'], $a['date'])
                    && $this->eligibility->isEligible($employeeB, $a['workcenter_id'], $a['shift_id'], $a['date'], $assignments);

                $assignments->add($a['employee_id'], $a['workcenter_id'], $a['shift_id'], $a['date']);
                $assignments->add($b['employee_id'], $b['workcenter_id'], $b['shift_id'], $b['date']);

                if ($aCanTakeB && $bCanTakeA) {
                    $moves[] = ['type' => 'swap', 'a' => $a, 'b' => $b];
                }
            }
        }

        return $moves;
    }

    private function apply(array $move, PlanAssignmentSet $assignments): void
    {
        if ($move['type'] === 'fill') {
            $assignments->add($move['employee_id'], $move['workcenter_id'], $move['shift_id'], $move['date']);

            return;
        }

        if ($move['type'] === 'relocate') {
            $assignments->remove($move['employee_id'], $move['from']['workcenter_id'], $move['from']['shift_id'], $move['from']['date']);
            $assignments->add($move['employee_id'], $move['to']['workcenter_id'], $move['to']['shift_id'], $move['to']['date']);

            return;
        }

        if ($move['type'] === 'substitute') {
            $assignments->remove($move['from_employee_id'], $move['cell']['workcenter_id'], $move['cell']['shift_id'], $move['cell']['date']);
            $assignments->add($move['to_employee_id'], $move['cell']['workcenter_id'], $move['cell']['shift_id'], $move['cell']['date']);

            return;
        }

        // swap
        $assignments->remove($move['a']['employee_id'], $move['a']['workcenter_id'], $move['a']['shift_id'], $move['a']['date']);
        $assignments->remove($move['b']['employee_id'], $move['b']['workcenter_id'], $move['b']['shift_id'], $move['b']['date']);
        $assignments->add($move['a']['employee_id'], $move['b']['workcenter_id'], $move['b']['shift_id'], $move['b']['date']);
        $assignments->add($move['b']['employee_id'], $move['a']['workcenter_id'], $move['a']['shift_id'], $move['a']['date']);
    }

    private function revert(array $move, PlanAssignmentSet $assignments): void
    {
        if ($move['type'] === 'fill') {
            $assignments->remove($move['employee_id'], $move['workcenter_id'], $move['shift_id'], $move['date']);

            return;
        }

        if ($move['type'] === 'relocate') {
            $assignments->remove($move['employee_id'], $move['to']['workcenter_id'], $move['to']['shift_id'], $move['to']['date']);
            $assignments->add($move['employee_id'], $move['from']['workcenter_id'], $move['from']['shift_id'], $move['from']['date']);

            return;
        }

        if ($move['type'] === 'substitute') {
            $assignments->remove($move['to_employee_id'], $move['cell']['workcenter_id'], $move['cell']['shift_id'], $move['cell']['date']);
            $assignments->add($move['from_employee_id'], $move['cell']['workcenter_id'], $move['cell']['shift_id'], $move['cell']['date']);

            return;
        }

        // swap: undo by moving each employee back from the other's cell to their own
        $assignments->remove($move['a']['employee_id'], $move['b']['workcenter_id'], $move['b']['shift_id'], $move['b']['date']);
        $assignments->remove($move['b']['employee_id'], $move['a']['workcenter_id'], $move['a']['shift_id'], $move['a']['date']);
        $assignments->add($move['a']['employee_id'], $move['a']['workcenter_id'], $move['a']['shift_id'], $move['a']['date']);
        $assignments->add($move['b']['employee_id'], $move['b']['workcenter_id'], $move['b']['shift_id'], $move['b']['date']);
    }
}
