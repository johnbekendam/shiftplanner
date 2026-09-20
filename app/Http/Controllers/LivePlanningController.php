<?php

namespace App\Http\Controllers;

use App\Models\PublishedWeek;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * The wall-screen page of one workcenter, reached by its secret live token.
 * No authentication — see features/workcenter-live-planning/. It shows the
 * current and the next week, and only a week a manager published for this
 * workcenter. An unpublished week carries no shifts and no names.
 */
class LivePlanningController extends Controller
{
    /** The days a week block shows, as offsets from Monday. */
    private const DAY_COUNT = 5;

    private const WEEK_COUNT = 2;

    public function show(Request $request, string $token)
    {
        $workcenter = Workcenter::query()
            ->where('live_token', $token)
            ->whereNull('archived_at')
            ->firstOrFail();

        $now = now();
        $firstWeek = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekStarts = collect(range(0, self::WEEK_COUNT - 1))->map(fn (int $i) => $firstWeek->copy()->addWeeks($i));

        $published = PublishedWeek::query()
            ->where('workcenter_id', $workcenter->id)
            ->whereIn('week_start', $weekStarts->map->toDateString())
            ->get()
            ->map(fn (PublishedWeek $p) => $p->week_start->toDateString())
            ->flip();

        return Inertia::render('Live', [
            'workcenter' => ['name' => $workcenter->name],
            'today' => $now->toDateString(),
            'generatedAt' => $now->toIso8601String(),
            'weeks' => $weekStarts
                ->map(fn (Carbon $weekStart) => $this->week($workcenter, $weekStart, $published->has($weekStart->toDateString())))
                ->all(),
        ])->toResponse($request)->withHeaders([
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @return array{weekStart: string, published: bool, days: array, shifts: array} */
    private function week(Workcenter $workcenter, Carbon $weekStart, bool $published): array
    {
        $days = collect(range(0, self::DAY_COUNT - 1))->map(fn (int $i) => $weekStart->copy()->addDays($i));

        return [
            'weekStart' => $weekStart->toDateString(),
            'published' => $published,
            'days' => $days->map->toDateString()->all(),
            'shifts' => $published ? $this->shifts($workcenter, $days) : [],
        ];
    }

    private function shifts(Workcenter $workcenter, Collection $days): array
    {
        $first = $days->first()->toDateString();
        $last = $days->last()->toDateString();

        $capacities = WorkcenterShiftCapacity::query()
            ->where('workcenter_id', $workcenter->id)
            ->get()
            ->keyBy(fn (WorkcenterShiftCapacity $c) => "{$c->shift_id}:{$c->weekday}");

        $overrides = WorkcenterShiftDateOverride::query()
            ->where('workcenter_id', $workcenter->id)
            ->whereBetween('date', [$first, $last])
            ->get()
            ->keyBy(fn (WorkcenterShiftDateOverride $o) => "{$o->shift_id}:{$o->date->toDateString()}");

        $assignments = ShiftAssignment::query()
            ->where('workcenter_id', $workcenter->id)
            ->whereBetween('date', [$first, $last])
            ->with('employee')
            ->get()
            ->groupBy(fn (ShiftAssignment $a) => "{$a->shift_id}:{$a->date->toDateString()}");

        return $workcenter->shifts->map(fn ($shift) => [
            'id' => $shift->id,
            'name' => $shift->name,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'cells' => $days->map(function (Carbon $date) use ($shift, $capacities, $overrides, $assignments) {
                $dateStr = $date->toDateString();
                $spots = $overrides->get("{$shift->id}:{$dateStr}")?->spots
                    ?? $capacities->get("{$shift->id}:{$date->isoWeekday()}")?->spots
                    ?? 0;
                $names = $assignments->get("{$shift->id}:{$dateStr}", collect())
                    ->map(fn (ShiftAssignment $a) => $a->employee->name)
                    ->sort()
                    ->values();

                return [
                    'date' => $dateStr,
                    'spots' => $spots,
                    'names' => $names->all(),
                    'open' => max(0, $spots - $names->count()),
                ];
            })->values()->all(),
        ])->values()->all();
    }
}
