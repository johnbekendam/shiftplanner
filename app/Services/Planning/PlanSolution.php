<?php

namespace App\Services\Planning;

/**
 * What the planner decided for the cycle's remaining (non-locked)
 * capacity — not a diff. {@see HeuristicPlanGenerator::apply()} computes
 * the diff against what's currently in the database.
 */
final readonly class PlanSolution
{
    public function __construct(
        /** @var array<int, array{employee_id: int, workcenter_id: int, shift_id: int, date: string}> */
        public array $assignments,
        /** @var array<int, array{workcenter_id: int, shift_id: int, date: string, reason: string}> */
        public array $unfulfilled,
    ) {}
}
