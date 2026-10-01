<?php

namespace App\Services;

use App\Models\BusinessLine;
use App\Models\PublishedWeek;
use App\Models\ShiftAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The page props of the schedule: who works on which day of one ISO week.
 * Shared by the logged-in page and the public link. See features/roster/
 * and features/roster-public-link/.
 */
class ScheduleWeek
{
    public function props(Request $request): array
    {
        $now = now();
        $weekStart = $this->weekStart($request, $now);
        $days = collect(range(0, 6))->map(fn (int $i) => $weekStart->copy()->addDays($i));
        $businessLines = BusinessLine::all(); // position-ordered by the model scope
        $filter = BusinessLineFilter::fromRequest($request, $businessLines);

        return [
            'weekStart' => $weekStart->toDateString(),
            'weekNumber' => $weekStart->isoWeek,
            'today' => $now->toDateString(),
            'days' => $days->map->toDateString()->all(),
            'rows' => $this->rows($weekStart, $days, $filter),
            'businessLines' => $businessLines->map(fn (BusinessLine $line) => [
                'id' => $line->id,
                'abbreviation' => $line->abbreviation,
            ])->all(),
            'selectedBusinessLines' => $filter->selected(),
        ];
    }

    /**
     * One row per employee with a published assignment in the week, sorted by
     * name. `days` holds seven lists of { shift, workcenter }, Monday first,
     * each in shift start order. An assignment shows only when its own
     * (week, workcenter) pair is published. $filter limits the employees.
     */
    private function rows(Carbon $weekStart, Collection $days, BusinessLineFilter $filter): array
    {
        $publishedWorkcenterIds = PublishedWeek::query()
            ->where('week_start', $weekStart->toDateString())
            ->pluck('workcenter_id');

        $dates = $days->map->toDateString();

        $assignments = ShiftAssignment::query()
            ->whereIn('workcenter_id', $publishedWorkcenterIds)
            ->whereBetween('date', [$dates->first(), $dates->last()])
            ->whereHas('employee', fn ($q) => $filter->apply($q, 'business_line_id'))
            ->with(['employee.businessLine', 'shift', 'workcenter'])
            ->get();

        return $assignments
            ->groupBy('employee_id')
            ->sortBy(fn (Collection $own) => [$own->first()->employee->first_name, $own->first()->employee->last_name])
            ->map(function (Collection $own) use ($dates) {
                $employee = $own->first()->employee;
                $byDate = $own
                    ->sortBy(fn (ShiftAssignment $a) => [$a->shift->start_time, $a->shift->name])
                    ->groupBy(fn (ShiftAssignment $a) => $a->date->toDateString());

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'business_line' => $employee->businessLine?->abbreviation,
                    'days' => $dates->map(fn (string $date) => $byDate->get($date, collect())
                        ->map(fn (ShiftAssignment $a) => ['shift' => $a->shift->name, 'workcenter' => $a->workcenter->name])
                        ->values()
                        ->all())->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** The Monday of the `?week=` date, or of the current week when it is missing or invalid. */
    private function weekStart(Request $request, Carbon $now): Carbon
    {
        $week = $request->query('week');
        $date = is_string($week) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)
            ? rescue(fn () => Carbon::createFromFormat('!Y-m-d', $week), report: false)
            : null;

        return ($date ?: $now->copy())->startOfWeek(Carbon::MONDAY)->startOfDay();
    }
}
