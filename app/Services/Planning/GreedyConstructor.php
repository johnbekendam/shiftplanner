<?php

namespace App\Services\Planning;

/**
 * Fills as many open spots as possible, hard constraints only — no
 * hill-climbing, no soft terms. Spots are processed most-constrained-first
 * (fewest eligible candidates first) so the hardest-to-satisfy cells don't
 * lose their only candidates to an easier cell filled earlier. Each spot's
 * candidate is the currently-least-loaded eligible employee, so the result
 * starts close to workload-balanced before any optimization pass runs.
 */
final class GreedyConstructor
{
    public function __construct(
        private readonly PlanProblem $problem,
        private readonly PlanEligibility $eligibility,
    ) {}

    /** Mutates $assignments in place (already seeded with the cycle's current state). */
    public function construct(PlanAssignmentSet $assignments): void
    {
        while (true) {
            $mostConstrained = null;
            $mostConstrainedCandidates = [];

            foreach ($this->openCells($assignments) as $cell) {
                $candidates = $this->eligibleCandidates($cell, $assignments);
                if ($candidates === []) {
                    continue;
                }
                $assignments->noteCandidateSeen($cell['workcenter_id'], $cell['shift_id'], $cell['date']);
                if ($mostConstrained === null || count($candidates) < count($mostConstrainedCandidates)) {
                    $mostConstrained = $cell;
                    $mostConstrainedCandidates = $candidates;
                }
            }

            if ($mostConstrained === null) {
                break; // every remaining open cell has zero eligible candidates
            }

            $chosen = $this->leastLoaded($mostConstrainedCandidates, $assignments);
            $assignments->add($chosen['id'], $mostConstrained['workcenter_id'], $mostConstrained['shift_id'], $mostConstrained['date']);
        }
    }

    /** @return array<int, array{workcenter_id: int, shift_id: int, date: string, spots: int, locked: int}> */
    private function openCells(PlanAssignmentSet $assignments): array
    {
        return array_values(array_filter(
            $this->problem->spots,
            fn (array $spot) => $assignments->countForCell($spot['workcenter_id'], $spot['shift_id'], $spot['date']) < $spot['spots'],
        ));
    }

    /** @return array<int, array{id: int, weekly_hours: int}> */
    private function eligibleCandidates(array $cell, PlanAssignmentSet $assignments): array
    {
        $candidates = [];
        foreach ($this->problem->employees as $employee) {
            if ($assignments->isAssigned($employee['id'], $cell['workcenter_id'], $cell['shift_id'], $cell['date'])) {
                continue;
            }
            if ($this->eligibility->isEligible($employee, $cell['workcenter_id'], $cell['shift_id'], $cell['date'], $assignments)) {
                $candidates[] = $employee;
            }
        }

        return $candidates;
    }

    private function leastLoaded(array $candidates, PlanAssignmentSet $assignments): array
    {
        usort($candidates, fn (array $a, array $b) => $assignments->totalHours($a['id']) <=> $assignments->totalHours($b['id']));

        return $candidates[0];
    }
}
