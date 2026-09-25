<?php

namespace App\Services;

use App\Models\PlanningRule;
use App\Models\ShiftAssignment;
use App\Services\Planning\PlanningCycle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds every hard-constraint violation of the stored assignments in one
 * week, for the planning page's Verify button. Unlike
 * {@see SchedulingEligibility}, which tests one proposed assignment and
 * stops at the first failure, this checks existing assignments and lists
 * all violations of each.
 */
class PlanningVerifier
{
    /** @var array<int, string[]> assignment id => violation codes */
    private array $violations = [];

    /** @var array<int, true> ids of the assignments this run verifies */
    private array $verifiedIds = [];

    /** @return array<int, string[]> assignment id => violation codes; violating assignments only */
    public function verifyWeek(Carbon $weekStart): array
    {
        $this->violations = [];
        $weekStart = $weekStart->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $rules = PlanningRule::query()->where('mode', 'hard')->get();

        $assignments = ShiftAssignment::query()
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->whereHas('workcenter', fn ($q) => $q->whereNull('archived_at'))
            ->with([
                'shift',
                'workcenter.shifts',
                'employee.holidays',
                'employee.recurringAvailabilities',
                'employee.workcenters',
                'employee.competences',
            ])
            ->get();

        $this->verifiedIds = $assignments->mapWithKeys(fn (ShiftAssignment $a) => [$a->id => true])->all();

        foreach ($assignments as $assignment) {
            $this->checkSingle($assignment, $rules);
        }

        // Group checks count the employee's shifts in every workcenter,
        // archived ones included, but only mark the verified assignments.
        $weekByEmployee = ShiftAssignment::query()
            ->whereIn('employee_id', $assignments->pluck('employee_id')->unique())
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with(['shift', 'employee'])
            ->get()
            ->groupBy('employee_id');

        foreach ($weekByEmployee as $weekAssignments) {
            $this->checkOverlap($weekAssignments);
            $this->checkMaxShiftsPerDay($weekAssignments, $rules);
            $this->checkMaxHours($weekAssignments, $rules, $weekStart);
            $this->checkAlternatingPairs($weekAssignments, $rules);
        }

        $this->checkOverfilledCells($assignments);

        return $this->violations;
    }

    /** Matches SchedulingEligibility::hasOverlap: raw start/end string comparison. */
    private function checkOverlap(Collection $weekAssignments): void
    {
        foreach ($weekAssignments->groupBy(fn (ShiftAssignment $a) => $a->date->toDateString()) as $day) {
            foreach ($day as $a) {
                foreach ($day as $b) {
                    if ($a->id !== $b->id
                        && $a->shift->getRawOriginal('start_time') < $b->shift->getRawOriginal('end_time')
                        && $b->shift->getRawOriginal('start_time') < $a->shift->getRawOriginal('end_time')) {
                        $this->flag($a, 'overlap');
                    }
                }
            }
        }
    }

    private function checkMaxShiftsPerDay(Collection $weekAssignments, Collection $rules): void
    {
        $rule = $rules->firstWhere('type', 'max_shifts_per_day');
        if ($rule === null) {
            return;
        }

        foreach ($weekAssignments->groupBy(fn (ShiftAssignment $a) => $a->date->toDateString()) as $day) {
            if ($day->count() > (int) $rule->config['value']) {
                $this->flagAll($day, 'max_shifts_per_day');
            }
        }
    }

    /** Same caps as SchedulingEligibility::hardCapViolation, applied to totals that already exist. */
    private function checkMaxHours(Collection $weekAssignments, Collection $rules, Carbon $weekStart): void
    {
        if (! $rules->contains('type', 'max_hours_per_week')) {
            return;
        }

        $employee = $weekAssignments->first()->employee;
        $capHours = fn (Collection $list) => $list->sum(fn (ShiftAssignment $a) => $a->shift->capHours());

        $cycleStart = PlanningCycle::containing($weekStart);
        if ($cycleStart !== null) {
            $cycleAssignments = ShiftAssignment::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('date', [$cycleStart->toDateString(), $cycleStart->copy()->addDays(13)->toDateString()])
                ->with('shift')
                ->get();

            if ($capHours($cycleAssignments) > $employee->weekly_hours * 2) {
                $this->flagAll($weekAssignments, 'max_hours_per_week');
            }
        }

        if ($capHours($weekAssignments) > $employee->weekly_hours + 4) {
            $this->flagAll($weekAssignments, 'max_hours_per_week_distribution');
        }
    }

    private function checkAlternatingPairs(Collection $weekAssignments, Collection $rules): void
    {
        foreach ($rules->where('type', 'alternating_shift_pair') as $pair) {
            $shiftIds = [(int) $pair->config['first_shift_id'], (int) $pair->config['second_shift_id']];
            $pairAssignments = $weekAssignments->filter(fn (ShiftAssignment $a) => in_array($a->shift_id, $shiftIds, true));

            if ($pairAssignments->pluck('shift_id')->unique()->count() === 2) {
                $this->flagAll($pairAssignments, 'alternating_shift_pair');
            }
        }
    }

    private function checkOverfilledCells(Collection $assignments): void
    {
        $cells = $assignments->groupBy(fn (ShiftAssignment $a) => "{$a->workcenter_id}:{$a->shift_id}:{$a->date->toDateString()}");

        foreach ($cells as $cell) {
            $first = $cell->first();
            if ($cell->count() > $first->workcenter->spotsFor($first->shift, $first->date)) {
                $this->flagAll($cell, 'cell_overfilled');
            }
        }
    }

    private function checkSingle(ShiftAssignment $assignment, Collection $rules): void
    {
        $employee = $assignment->employee;
        $date = $assignment->date->toDateString();
        $isMember = $employee->workcenters->contains('id', $assignment->workcenter_id);

        if (! $employee->confirmed) {
            $this->flag($assignment, 'unconfirmed');
        }

        if ($employee->archived_at !== null) {
            $this->flag($assignment, 'archived');
        }

        if ($employee->holidays->contains(fn ($h) => $h->start_date->toDateString() <= $date && $h->end_date->toDateString() >= $date)) {
            $this->flag($assignment, 'holiday');
        }

        // Matches SchedulingEligibility: a missing cell is unavailable, not available.
        $level = $employee->recurringAvailabilities
            ->first(fn ($r) => $r->weekday === $assignment->date->isoWeekday() && $r->shift_id === $assignment->shift_id)
            ?->level;
        if (! in_array($level, ['available', 'not_preferred'], true)) {
            $this->flag($assignment, 'unavailable');
        }
        if ($level === 'not_preferred' && $rules->contains('type', 'not_preferred_shift')) {
            $this->flag($assignment, 'not_preferred');
        }

        if (! $isMember) {
            $this->flag($assignment, 'workcenter_ineligible');
        }

        // Matches SchedulingEligibility::isShiftUnavailableForWorkcenter.
        if (! $assignment->shift->visible_by_default
            && ! ($isMember && $assignment->workcenter->shifts->contains('id', $assignment->shift_id))) {
            $this->flag($assignment, 'shift_hidden');
        }

        foreach ($this->rulesFor($rules, 'competence_required', $assignment->workcenter_id) as $rule) {
            if (! $employee->competences->contains('id', $rule->config['competence_id'])) {
                $this->flag($assignment, 'competence_required');
            }
        }

        foreach ($this->rulesFor($rules, 'business_line_preference', $assignment->workcenter_id) as $rule) {
            if ($employee->business_line_id !== $rule->config['business_line_id']) {
                $this->flag($assignment, 'business_line_preference');
            }
        }
    }

    private function rulesFor(Collection $rules, string $type, int $workcenterId): Collection
    {
        return $rules->filter(fn (PlanningRule $r) => $r->type === $type && (int) $r->config['workcenter_id'] === $workcenterId);
    }

    private function flagAll(Collection $assignments, string $code): void
    {
        foreach ($assignments as $assignment) {
            $this->flag($assignment, $code);
        }
    }

    private function flag(ShiftAssignment $assignment, string $code): void
    {
        if (! isset($this->verifiedIds[$assignment->id])) {
            return;
        }

        if (! in_array($code, $this->violations[$assignment->id] ?? [], true)) {
            $this->violations[$assignment->id][] = $code;
        }
    }
}
