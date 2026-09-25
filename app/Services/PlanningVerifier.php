<?php

namespace App\Services;

use App\Models\PlanningRule;
use App\Models\ShiftAssignment;
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

        foreach ($assignments as $assignment) {
            $this->checkSingle($assignment, $rules);
        }

        return $this->violations;
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

    private function flag(ShiftAssignment $assignment, string $code): void
    {
        if (! in_array($code, $this->violations[$assignment->id] ?? [], true)) {
            $this->violations[$assignment->id][] = $code;
        }
    }
}
