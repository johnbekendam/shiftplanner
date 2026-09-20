<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PlanGenerationRun;
use App\Models\PlanningSettings;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use App\Services\Planning\PlanningCycle;
use App\Services\UninformedPlanning;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SchedulingController extends Controller
{
    public function __construct(private UninformedPlanning $uninformedPlanning) {}

    public function index(Request $request)
    {
        $now = Carbon::now();
        $year = $request->integer('year') ?: $now->year;
        $month = $request->integer('month') ?: $now->month;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();

        $selectedDate = $this->resolveSelectedDate($request, $monthStart, $now);
        $weekStart = $selectedDate->copy()->startOfWeek(Carbon::MONDAY);

        $workcenters = Workcenter::query()->whereNull('archived_at')->get();
        $shifts = Shift::query()->get();
        $workcenterIds = $workcenters->pluck('id');

        $attachments = DB::table('workcenter_shift')
            ->whereIn('workcenter_id', $workcenterIds)
            ->get(['workcenter_id', 'shift_id']);

        $capacities = WorkcenterShiftCapacity::query()
            ->whereIn('workcenter_id', $workcenterIds)
            ->get()
            ->keyBy(fn (WorkcenterShiftCapacity $c) => "{$c->workcenter_id}:{$c->shift_id}:{$c->weekday}");

        $cycleStart = PlanningCycle::containing($weekStart);

        return Inertia::render('Scheduling', [
            'workcenters' => $workcenters->map(fn (Workcenter $w) => ['id' => $w->id, 'name' => $w->name])->values()->all(),
            'shifts' => $shifts->map(fn (Shift $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
            ])->values()->all(),
            'year' => $monthStart->year,
            'month' => $monthStart->month,
            'coverage' => $this->coverage($attachments, $capacities, $workcenterIds, $monthStart),
            'date' => $selectedDate->toDateString(),
            'weekStart' => $weekStart->toDateString(),
            'weekCells' => $this->weekCells($attachments, $capacities, $workcenterIds, $weekStart),
            'publishedWorkcenterWeeks' => $this->publishedWorkcenterWeeks($monthStart, $workcenterIds),
            'cycleStart' => $cycleStart?->toDateString(),
            'generationRun' => $cycleStart ? $this->latestGenerationRun($cycleStart) : null,
            'planningPeriod' => $this->planningPeriod(),
            'generationStatus' => $this->generationStatus(),
            // Employees with published shifts they were not told about and no queued email yet: enables Send planning.
            'uninformedCount' => $this->uninformedPlanning->summary(excludeQueued: true)->count(),
        ]);
    }

    /** { start, end } from Settings, or null until both are configured. */
    private function planningPeriod(): ?array
    {
        $settings = PlanningSettings::current();
        if ($settings->period_start === null || $settings->period_end === null) {
            return null;
        }

        return [
            'start' => $settings->period_start->toDateString(),
            'end' => $settings->period_end->toDateString(),
        ];
    }

    /**
     * Aggregate across every cycle in the planning period, for the Generate
     * button: active if any cycle's latest run is pending/running; failed
     * (with the first error) if none are active but at least one cycle's
     * latest run failed. Null when the period isn't configured.
     */
    private function generationStatus(): ?array
    {
        $cycles = PlanningCycle::allWithinPeriod();
        if ($cycles === []) {
            return null;
        }

        $cycleStarts = collect($cycles)->map(fn (Carbon $c) => $c->toDateString());

        $latestPerCycle = PlanGenerationRun::query()
            ->whereIn('cycle_start', $cycleStarts)
            ->orderByDesc('id')
            ->get()
            ->unique('cycle_start');

        $active = $latestPerCycle->contains(fn (PlanGenerationRun $run) => in_array($run->status, PlanGenerationRun::ACTIVE_STATUSES, true));
        $failed = $latestPerCycle->filter(fn (PlanGenerationRun $run) => $run->status === PlanGenerationRun::STATUS_FAILED)->values();

        return [
            'active' => $active,
            'failedCount' => $failed->count(),
            'firstError' => $failed->first()?->error,
        ];
    }

    /** The most recent generation run for this cycle, or null if none has ever run. */
    private function latestGenerationRun(Carbon $cycleStart): ?array
    {
        $run = PlanGenerationRun::query()
            ->whereDate('cycle_start', $cycleStart)
            ->latest('id')
            ->first();

        if (! $run) {
            return null;
        }

        return [
            'id' => $run->id,
            'status' => $run->status,
            'error' => $run->error,
            'changes' => $this->resolveChanges($run->changes ?? []),
            'unfulfilled' => $this->resolveUnfulfilled($run->unfulfilled ?? []),
        ];
    }

    /** @return array<int, array{type: string, employee_id: int, employee_name: string, workcenter_name: string, shift_name: string, date: string}> */
    private function resolveChanges(array $changes): array
    {
        if ($changes === []) {
            return [];
        }

        $employees = Employee::query()->whereIn('id', collect($changes)->pluck('employee_id')->unique())->get()->keyBy('id');
        $workcenters = Workcenter::query()->whereIn('id', collect($changes)->pluck('workcenter_id')->unique())->get()->keyBy('id');
        $shifts = Shift::query()->whereIn('id', collect($changes)->pluck('shift_id')->unique())->get()->keyBy('id');

        return collect($changes)->map(fn (array $c) => [
            'type' => $c['type'],
            'employee_id' => $c['employee_id'],
            'employee_name' => $employees->get($c['employee_id'])?->name ?? "#{$c['employee_id']}",
            'workcenter_name' => $workcenters->get($c['workcenter_id'])?->name ?? "#{$c['workcenter_id']}",
            'shift_name' => $shifts->get($c['shift_id'])?->name ?? "#{$c['shift_id']}",
            'date' => $c['date'],
        ])->values()->all();
    }

    /** @return array<int, array{workcenter_id: int, shift_id: int, workcenter_name: string, shift_name: string, date: string, reason: string}> */
    private function resolveUnfulfilled(array $unfulfilled): array
    {
        if ($unfulfilled === []) {
            return [];
        }

        $workcenters = Workcenter::query()->whereIn('id', collect($unfulfilled)->pluck('workcenter_id')->unique())->get()->keyBy('id');
        $shifts = Shift::query()->whereIn('id', collect($unfulfilled)->pluck('shift_id')->unique())->get()->keyBy('id');

        return collect($unfulfilled)->map(fn (array $u) => [
            'workcenter_id' => $u['workcenter_id'],
            'shift_id' => $u['shift_id'],
            'workcenter_name' => $workcenters->get($u['workcenter_id'])?->name ?? "#{$u['workcenter_id']}",
            'shift_name' => $shifts->get($u['shift_id'])?->name ?? "#{$u['shift_id']}",
            'date' => $u['date'],
            'reason' => $u['reason'],
        ])->values()->all();
    }

    /** [{ workcenter_id, week_start }] for every (workcenter, week) published within the visible month. */
    private function publishedWorkcenterWeeks(Carbon $monthStart, Collection $workcenterIds): array
    {
        $weekStarts = collect(range(1, $monthStart->daysInMonth))
            ->map(fn (int $day) => $monthStart->copy()->day($day)->startOfWeek(Carbon::MONDAY)->toDateString())
            ->unique()
            ->values();

        return PublishedWeek::query()
            ->whereIn('week_start', $weekStarts)
            ->whereIn('workcenter_id', $workcenterIds)
            ->get(['week_start', 'workcenter_id', 'planner_open'])
            ->map(fn (PublishedWeek $p) => [
                'workcenter_id' => $p->workcenter_id,
                'week_start' => $p->week_start->toDateString(),
                'planner_open' => $p->planner_open,
            ])
            ->values()
            ->all();
    }

    private function resolveSelectedDate(Request $request, Carbon $monthStart, Carbon $now): Carbon
    {
        $given = $request->query('date');
        if (is_string($given) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $given)) {
            return Carbon::parse($given);
        }

        if ($monthStart->year === $now->year && $monthStart->month === $now->month) {
            return $now->copy()->startOfDay();
        }

        return $monthStart->copy();
    }

    /** One entry per (workcenter, shift, date) with spots > 0 that date: { workcenter_id, shift_id, date, spots, assigned }. */
    private function coverage(Collection $attachments, Collection $capacities, Collection $workcenterIds, Carbon $monthStart): array
    {
        if ($workcenterIds->isEmpty()) {
            return [];
        }

        $monthEnd = $monthStart->copy()->endOfMonth();
        $days = collect(range(0, $monthStart->daysInMonth - 1))
            ->map(fn (int $offset) => $monthStart->copy()->addDays($offset));

        $overrides = WorkcenterShiftDateOverride::query()
            ->whereIn('workcenter_id', $workcenterIds)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn (WorkcenterShiftDateOverride $o) => "{$o->workcenter_id}:{$o->shift_id}:{$o->date->toDateString()}");

        $assignedCounts = ShiftAssignment::query()
            ->whereIn('workcenter_id', $workcenterIds)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->countBy(fn (ShiftAssignment $a) => "{$a->workcenter_id}:{$a->shift_id}:{$a->date->toDateString()}");

        $coverage = [];
        foreach ($attachments as $attachment) {
            foreach ($days as $date) {
                $dateStr = $date->toDateString();
                $key = "{$attachment->workcenter_id}:{$attachment->shift_id}:{$dateStr}";
                $spots = $this->spotsFor($attachment, $date, $overrides, $capacities);

                if ($spots <= 0) {
                    continue;
                }

                $coverage[] = [
                    'workcenter_id' => $attachment->workcenter_id,
                    'shift_id' => $attachment->shift_id,
                    'date' => $dateStr,
                    'spots' => $spots,
                    'assigned' => $assignedCounts->get($key, 0),
                ];
            }
        }

        return $coverage;
    }

    /**
     * One entry per (workcenter, shift, date) for every attached pair across the whole
     * week, including zero-spot days: { workcenter_id, shift_id, date, spots, overridden,
     * assignments: [{ id, employee_id, employee_name, fixed }] }.
     */
    private function weekCells(Collection $attachments, Collection $capacities, Collection $workcenterIds, Carbon $weekStart): array
    {
        if ($workcenterIds->isEmpty()) {
            return [];
        }

        $weekEnd = $weekStart->copy()->addDays(6);
        $days = collect(range(0, 6))->map(fn (int $offset) => $weekStart->copy()->addDays($offset));

        $overrides = WorkcenterShiftDateOverride::query()
            ->whereIn('workcenter_id', $workcenterIds)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->keyBy(fn (WorkcenterShiftDateOverride $o) => "{$o->workcenter_id}:{$o->shift_id}:{$o->date->toDateString()}");

        $assignments = ShiftAssignment::query()
            ->whereIn('workcenter_id', $workcenterIds)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with('employee')
            ->get()
            ->groupBy(fn (ShiftAssignment $a) => "{$a->workcenter_id}:{$a->shift_id}:{$a->date->toDateString()}");

        $cells = [];
        foreach ($attachments as $attachment) {
            foreach ($days as $date) {
                $dateStr = $date->toDateString();
                $key = "{$attachment->workcenter_id}:{$attachment->shift_id}:{$dateStr}";

                $cells[] = [
                    'workcenter_id' => $attachment->workcenter_id,
                    'shift_id' => $attachment->shift_id,
                    'date' => $dateStr,
                    'spots' => $this->spotsFor($attachment, $date, $overrides, $capacities),
                    'overridden' => $overrides->has($key),
                    'assignments' => $assignments->get($key, collect())
                        ->map(fn (ShiftAssignment $a) => $a->toPayload())
                        ->values()
                        ->all(),
                ];
            }
        }

        return $cells;
    }

    private function spotsFor(object $attachment, Carbon $date, Collection $overrides, Collection $capacities): int
    {
        $key = "{$attachment->workcenter_id}:{$attachment->shift_id}:{$date->toDateString()}";

        return $overrides->get($key)?->spots
            ?? $capacities->get("{$attachment->workcenter_id}:{$attachment->shift_id}:{$date->isoWeekday()}")?->spots
            ?? 0;
    }
}
