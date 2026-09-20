<?php

namespace App\Services\Planning;

use App\Models\Employee;
use App\Models\PlanGenerationRun;
use App\Models\PlanningRule;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HeuristicPlanGenerator implements PlanGeneratorContract
{
    public function generate(PlanGenerationRun $run): void
    {
        $cycleStart = $run->cycle_start->copy();
        $cycleEnd = $cycleStart->copy()->addDays(13);

        $workcenterIds = Workcenter::query()->whereNull('archived_at')->pluck('id');

        $cycleAssignments = ShiftAssignment::query()
            ->whereIn('workcenter_id', $workcenterIds)
            ->whereBetween('date', [$cycleStart->toDateString(), $cycleEnd->toDateString()])
            ->get();

        $isLocked = $this->lockPredicate($cycleStart, $workcenterIds);

        $problem = $this->buildProblem($cycleStart, $cycleEnd, $workcenterIds, $cycleAssignments, $isLocked);
        $eligibility = new PlanEligibility($problem);
        $softRules = new PlanSoftRules($problem->rules);
        $scorer = new PlanScorer($problem, $softRules);

        // Seeded with every current assignment in the cycle, locked or not — the
        // planner needs the true current occupancy to compute open capacity and
        // overlap/hour/day totals correctly. Construction never moves or removes
        // anything; hill-climbing (below) may relocate or swap a non-locked
        // assignment — including one already here before this run started — but
        // never a fixed or published one.
        $assignments = new PlanAssignmentSet($problem->shifts);
        foreach ($cycleAssignments as $a) {
            $assignments->add($a->employee_id, $a->workcenter_id, $a->shift_id, $a->date->toDateString(), $isLocked($a));
        }

        (new GreedyConstructor($problem, $eligibility))->construct($assignments);
        (new HillClimbOptimizer($problem, $eligibility, $scorer))->optimize($assignments);

        $unfulfilled = $this->unfulfilled($problem, $assignments);
        $solution = new PlanSolution($assignments->movable(), $unfulfilled);

        $this->apply($solution, $run, $cycleAssignments, $isLocked, $workcenterIds);
    }

    /**
     * Computed once, after both phases settle — hill-climbing's relocate
     * moves can shift *which* cells end up open even though they never
     * change how many are (see HillClimbOptimizer's docblock), so
     * construction's own open-cell list would be stale here.
     *
     * @return array<int, array{workcenter_id: int, shift_id: int, date: string, reason: string}>
     */
    private function unfulfilled(PlanProblem $problem, PlanAssignmentSet $assignments): array
    {
        $unfulfilled = [];
        foreach ($problem->spots as $spot) {
            if ($assignments->countForCell($spot['workcenter_id'], $spot['shift_id'], $spot['date']) >= $spot['spots']) {
                continue;
            }
            $unfulfilled[] = [
                'workcenter_id' => $spot['workcenter_id'],
                'shift_id' => $spot['shift_id'],
                'date' => $spot['date'],
                'reason' => $assignments->hadCandidate($spot['workcenter_id'], $spot['shift_id'], $spot['date'])
                    ? 'hard_cap_reached'
                    : 'no_eligible_employee',
            ];
        }

        return $unfulfilled;
    }

    /** True when $a must not be touched: fixed, or in a published (workcenter, week) pair. */
    private function lockPredicate(Carbon $cycleStart, Collection $workcenterIds): \Closure
    {
        $publishedPairs = PublishedWeek::lockedPairs($cycleStart, $cycleStart->copy()->addWeek(), $workcenterIds);

        return fn (ShiftAssignment $a) => $a->fixed
            || $publishedPairs->has("{$a->date->copy()->startOfWeek(Carbon::MONDAY)->toDateString()}:{$a->workcenter_id}");
    }

    private function buildProblem(
        Carbon $cycleStart,
        Carbon $cycleEnd,
        Collection $workcenterIds,
        Collection $cycleAssignments,
        \Closure $isLocked,
    ): PlanProblem {
        $allShifts = Shift::query()->get();
        $visibleShiftIds = $allShifts->where('visible_by_default', true)->pluck('id');

        $attachments = DB::table('workcenter_shift')
            ->whereIn('workcenter_id', $workcenterIds)
            ->whereIn('shift_id', $visibleShiftIds)
            ->get(['workcenter_id', 'shift_id']);

        $capacities = WorkcenterShiftCapacity::query()
            ->whereIn('workcenter_id', $workcenterIds)
            ->get()
            ->keyBy(fn (WorkcenterShiftCapacity $c) => "{$c->workcenter_id}:{$c->shift_id}:{$c->weekday}");

        $overrides = WorkcenterShiftDateOverride::query()
            ->whereIn('workcenter_id', $workcenterIds)
            ->whereBetween('date', [$cycleStart->toDateString(), $cycleEnd->toDateString()])
            ->get()
            ->keyBy(fn (WorkcenterShiftDateOverride $o) => "{$o->workcenter_id}:{$o->shift_id}:{$o->date->toDateString()}");

        $lockedAssignments = $cycleAssignments->filter($isLocked)
            ->map(fn (ShiftAssignment $a) => [
                'employee_id' => $a->employee_id,
                'workcenter_id' => $a->workcenter_id,
                'shift_id' => $a->shift_id,
                'date' => $a->date->toDateString(),
            ])
            ->values()
            ->all();

        $spots = $this->withoutFrozenSpots(
            $this->resolveSpots($attachments, $capacities, $overrides, $cycleStart, $cycleAssignments),
            PublishedWeek::frozenPairs($cycleStart, $cycleEnd, $workcenterIds),
        );

        $previousWeekStart = $cycleStart->copy()->subDays(7);
        $previousWeekEnd = $cycleStart->copy()->subDay();
        $previousWeekAssignments = ShiftAssignment::query()
            ->whereIn('workcenter_id', $workcenterIds)
            ->whereBetween('date', [$previousWeekStart->toDateString(), $previousWeekEnd->toDateString()])
            ->get()
            ->map(fn (ShiftAssignment $a) => [
                'employee_id' => $a->employee_id,
                'workcenter_id' => $a->workcenter_id,
                'shift_id' => $a->shift_id,
                'date' => $a->date->toDateString(),
            ])
            ->values()
            ->all();

        $employees = Employee::query()
            ->where('confirmed', true)
            ->where('weekly_hours', '>', 0)
            ->with(['holidays', 'recurringAvailabilities', 'workcenters', 'competences'])
            ->get()
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'weekly_hours' => $e->weekly_hours,
                'business_line_id' => $e->business_line_id,
                'holidays' => $e->holidays->map(fn ($h) => [
                    'start' => $h->start_date->toDateString(),
                    'end' => $h->end_date->toDateString(),
                ])->values()->all(),
                'recurring_availability' => $e->recurringAvailabilities->map(fn ($r) => [
                    'weekday' => $r->weekday,
                    'shift_id' => $r->shift_id,
                    'level' => $r->level,
                ])->values()->all(),
                'workcenters' => $e->workcenters->map(fn (Workcenter $w) => [
                    'workcenter_id' => $w->id,
                    'mode' => $w->pivot->mode,
                ])->values()->all(),
                'competences' => $e->competences->pluck('id')->values()->all(),
            ])
            ->values()
            ->all();

        $shifts = $allShifts->map(fn (Shift $s) => [
            'id' => $s->id,
            'start_time' => $s->start_time,
            'end_time' => $s->end_time,
        ])->values()->all();

        $rules = PlanningRule::all()->map(fn (PlanningRule $r) => $r->toPayload())->values()->all();

        return new PlanProblem(
            cycleStart: $cycleStart,
            cycleEnd: $cycleEnd,
            employees: $employees,
            shifts: $shifts,
            spots: $spots,
            lockedAssignments: $lockedAssignments,
            previousWeekAssignments: $previousWeekAssignments,
            rules: $rules,
        );
    }

    /**
     * Drops the spots of published workcenter-weeks that the planner may not fill.
     * Their assignments still count as locked; the spots simply are not planned,
     * so neither construction nor hill-climbing can place anyone there and no
     * unfulfilled spot is reported for them.
     *
     * @param  array<int, array{workcenter_id: int, shift_id: int, date: string, spots: int, locked: int}>  $spots
     * @return array<int, array{workcenter_id: int, shift_id: int, date: string, spots: int, locked: int}>
     */
    private function withoutFrozenSpots(array $spots, Collection $frozenPairs): array
    {
        return array_values(array_filter(
            $spots,
            fn (array $spot) => ! $frozenPairs->has(
                Carbon::parse($spot['date'])->startOfWeek(Carbon::MONDAY)->toDateString().":{$spot['workcenter_id']}"
            ),
        ));
    }

    /** @return array<int, array{workcenter_id: int, shift_id: int, date: string, spots: int, locked: int}> */
    private function resolveSpots(
        Collection $attachments,
        Collection $capacities,
        Collection $overrides,
        Carbon $cycleStart,
        Collection $cycleAssignments,
    ): array {
        $days = collect(range(0, 13))->map(fn (int $offset) => $cycleStart->copy()->addDays($offset));

        $lockedCounts = $cycleAssignments->countBy(
            fn (ShiftAssignment $a) => "{$a->workcenter_id}:{$a->shift_id}:{$a->date->toDateString()}",
        );

        $spots = [];
        foreach ($attachments as $attachment) {
            foreach ($days as $date) {
                $dateStr = $date->toDateString();
                $key = "{$attachment->workcenter_id}:{$attachment->shift_id}:{$dateStr}";

                $count = $overrides->get($key)?->spots
                    ?? $capacities->get("{$attachment->workcenter_id}:{$attachment->shift_id}:{$date->isoWeekday()}")?->spots
                    ?? 0;

                if ($count <= 0) {
                    continue;
                }

                $spots[] = [
                    'workcenter_id' => $attachment->workcenter_id,
                    'shift_id' => $attachment->shift_id,
                    'date' => $dateStr,
                    'spots' => $count,
                    'locked' => $lockedCounts->get($key, 0),
                ];
            }
        }

        return $spots;
    }

    private function apply(PlanSolution $solution, PlanGenerationRun $run, Collection $cycleAssignments, \Closure $isLocked, Collection $workcenterIds): void
    {
        DB::transaction(function () use ($solution, $run, $cycleAssignments, $isLocked, $workcenterIds) {
            $key = fn (array $a) => "{$a['employee_id']}:{$a['workcenter_id']}:{$a['shift_id']}:{$a['date']}";

            $previous = $cycleAssignments->reject($isLocked);
            // collect(...) first: Eloquent\Collection::except() filters by the model's
            // primary key, not by a custom keyBy() key — it would silently ignore these
            // string keys and return every row unfiltered. A plain Support\Collection's
            // except() does what's actually wanted here: filter by the keyBy() key.
            $previousByKey = collect($previous->all())->keyBy(fn (ShiftAssignment $a) => $key([
                'employee_id' => $a->employee_id,
                'workcenter_id' => $a->workcenter_id,
                'shift_id' => $a->shift_id,
                'date' => $a->date->toDateString(),
            ]));
            $newByKey = collect($solution->assignments)->keyBy($key);

            $toDelete = $previousByKey->except($newByKey->keys()->all());
            $toCreate = $newByKey->except($previousByKey->keys()->all());

            ShiftAssignment::query()->whereIn('id', $toDelete->pluck('id'))->delete();

            foreach ($toCreate as $a) {
                ShiftAssignment::create([
                    'employee_id' => $a['employee_id'],
                    'workcenter_id' => $a['workcenter_id'],
                    'shift_id' => $a['shift_id'],
                    'date' => $a['date'],
                    'fixed' => false,
                ]);
            }

            $changes = [
                ...$toDelete->values()->map(fn (ShiftAssignment $a) => [
                    'type' => 'removed',
                    'employee_id' => $a->employee_id,
                    'workcenter_id' => $a->workcenter_id,
                    'shift_id' => $a->shift_id,
                    'date' => $a->date->toDateString(),
                ])->all(),
                ...$toCreate->values()->map(fn (array $a) => [
                    'type' => 'added',
                    'employee_id' => $a['employee_id'],
                    'workcenter_id' => $a['workcenter_id'],
                    'shift_id' => $a['shift_id'],
                    'date' => $a['date'],
                ])->all(),
            ];

            // The permission from "Allow autoplanner" lasts for this one run.
            PublishedWeek::closePlannerFor($run->cycle_start, $workcenterIds);

            $run->update([
                'status' => PlanGenerationRun::STATUS_DONE,
                'changes' => $changes,
                'unfulfilled' => $solution->unfulfilled,
            ]);
        });
    }
}
