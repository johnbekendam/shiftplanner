<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SchedulingController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();
        $year = $request->integer('year') ?: $now->year;
        $month = $request->integer('month') ?: $now->month;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();

        $workcenters = Workcenter::query()->whereNull('archived_at')->get();
        $shifts = Shift::query()->get();

        return Inertia::render('Scheduling', [
            'workcenters' => $workcenters->map(fn (Workcenter $w) => ['id' => $w->id, 'name' => $w->name])->values()->all(),
            'shifts' => $shifts->map(fn (Shift $s) => ['id' => $s->id, 'name' => $s->name])->values()->all(),
            'year' => $monthStart->year,
            'month' => $monthStart->month,
            'coverage' => $this->coverage($workcenters, $monthStart),
        ]);
    }

    /** One entry per (workcenter, shift, date) with spots > 0 that date: { workcenter_id, shift_id, date, spots, assigned }. */
    private function coverage(Collection $workcenters, Carbon $monthStart): array
    {
        $workcenterIds = $workcenters->pluck('id');
        if ($workcenterIds->isEmpty()) {
            return [];
        }

        $monthEnd = $monthStart->copy()->endOfMonth();
        $days = collect(range(0, $monthStart->daysInMonth - 1))
            ->map(fn (int $offset) => $monthStart->copy()->addDays($offset));

        $attachments = DB::table('workcenter_shift')
            ->whereIn('workcenter_id', $workcenterIds)
            ->get(['workcenter_id', 'shift_id']);

        $capacities = WorkcenterShiftCapacity::query()
            ->whereIn('workcenter_id', $workcenterIds)
            ->get()
            ->keyBy(fn (WorkcenterShiftCapacity $c) => "{$c->workcenter_id}:{$c->shift_id}:{$c->weekday}");

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
                $override = $overrides->get($key);
                $spots = $override?->spots
                    ?? $capacities->get("{$attachment->workcenter_id}:{$attachment->shift_id}:{$date->isoWeekday()}")?->spots
                    ?? 0;

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
}
