<?php

namespace App\Services\Planning;

use Carbon\Carbon;

/**
 * Everything the planner reads for one cycle. Built once per run by
 * {@see HeuristicPlanGenerator}; every nested array element is a plain
 * associative array (not its own value object) — see spec.md for the
 * exact shape of each.
 */
final readonly class PlanProblem
{
    public function __construct(
        public Carbon $cycleStart,
        public Carbon $cycleEnd,
        /** @var array<int, array{id: int, weekly_hours: int, business_line_id: ?int, holidays: array, recurring_availability: array, workcenter_ids: int[], competences: array}> */
        public array $employees,
        /** @var array<int, array{id: int, start_time: string, end_time: string}> */
        public array $shifts,
        /** @var array<int, array{workcenter_id: int, shift_id: int, date: string, spots: int, locked: int}> */
        public array $spots,
        /** @var array<int, array{employee_id: int, workcenter_id: int, shift_id: int, date: string}> */
        public array $lockedAssignments,
        /** @var array<int, array{employee_id: int, workcenter_id: int, shift_id: int, date: string}> */
        public array $previousWeekAssignments,
        /** @var array<int, array{type: string, mode: ?string, severity: ?int, config: array}> */
        public array $rules,
    ) {}
}
