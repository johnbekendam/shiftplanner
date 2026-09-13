<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SchedulingController extends Controller
{
    public function index(Request $request)
    {
        $workcenters = Workcenter::query()->whereNull('archived_at')->get();

        $workcenterId = $this->resolveWorkcenterId($request, $workcenters);
        $weekStart = $this->resolveWeekStart($request);
        $days = collect(range(0, 6))->map(fn ($offset) => $weekStart->copy()->addDays($offset)->toDateString());

        $workcenter = $workcenterId ? $workcenters->firstWhere('id', $workcenterId) : null;
        $shifts = $workcenter ? $workcenter->shifts : collect();

        return Inertia::render('Scheduling', [
            'workcenters' => $workcenters->map(fn (Workcenter $w) => ['id' => $w->id, 'name' => $w->name])->values()->all(),
            'workcenterId' => $workcenterId,
            'weekStart' => $weekStart->toDateString(),
            'days' => $days->all(),
            'shifts' => $shifts->map(fn (Shift $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
            ])->values()->all(),
            'cells' => $workcenter ? $this->cells($workcenter, $shifts, $days) : [],
        ]);
    }

    private function resolveWorkcenterId(Request $request, $workcenters): ?int
    {
        $requested = $request->integer('workcenter_id') ?: null;
        if ($requested && $workcenters->contains('id', $requested)) {
            return $requested;
        }

        return $workcenters->first()?->id;
    }

    private function resolveWeekStart(Request $request): Carbon
    {
        $given = $request->query('week_start');
        $date = $given ? Carbon::parse($given) : Carbon::now();

        return $date->startOfWeek(Carbon::MONDAY);
    }

    /** One entry per shift x day: { shift_id, date, spots, assignments }. */
    private function cells(Workcenter $workcenter, $shifts, $days): array
    {
        $assignments = ShiftAssignment::query()
            ->where('workcenter_id', $workcenter->id)
            ->whereIn('shift_id', $shifts->pluck('id'))
            ->whereIn('date', $days)
            ->with('employee')
            ->get()
            ->groupBy(fn (ShiftAssignment $a) => "{$a->shift_id}:{$a->date->toDateString()}");

        $cells = [];
        foreach ($shifts as $shift) {
            foreach ($days as $date) {
                $cells[] = [
                    'shift_id' => $shift->id,
                    'date' => $date,
                    'spots' => $workcenter->spotsFor($shift, Carbon::parse($date)),
                    'assignments' => $assignments->get("{$shift->id}:{$date}", collect())
                        ->map(fn (ShiftAssignment $a) => $a->toPayload())
                        ->values()
                        ->all(),
                ];
            }
        }

        return $cells;
    }
}
