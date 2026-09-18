<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PublishedWeek;
use App\Models\ShiftAssignment;
use Carbon\Carbon;

/**
 * One employee's shift assignments grouped by week, for the Planning tabs
 * on both Personal/Show.vue (published only) and Employees/Form.vue
 * (everything, each assignment marked published or not).
 */
class PlannedShifts
{
    /** @return array<int, array{weekStart: string, weekEnd: string, assignments: array}> */
    public function forEmployee(Employee $employee, bool $publishedOnly): array
    {
        $assignments = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->with(['workcenter', 'shift'])
            ->orderBy('date')
            ->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        $weekKey = fn (ShiftAssignment $a) => $a->date->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        $publishedPairs = PublishedWeek::query()
            ->whereIn('week_start', $assignments->map($weekKey)->unique()->values())
            ->whereIn('workcenter_id', $assignments->pluck('workcenter_id')->unique()->values())
            ->get(['week_start', 'workcenter_id'])
            ->map(fn (PublishedWeek $p) => "{$p->week_start->toDateString()}:{$p->workcenter_id}")
            ->flip();

        $isPublished = fn (ShiftAssignment $a) => $publishedPairs->has("{$weekKey($a)}:{$a->workcenter_id}");

        $relevant = $publishedOnly ? $assignments->filter($isPublished)->values() : $assignments;

        if ($relevant->isEmpty()) {
            return [];
        }

        return $relevant->groupBy($weekKey)
            ->map(function ($group, string $weekStart) use ($isPublished) {
                $start = Carbon::parse($weekStart);

                return [
                    'weekStart' => $start->toDateString(),
                    'weekEnd' => $start->copy()->addDays(6)->toDateString(),
                    'assignments' => $group->map(fn (ShiftAssignment $a) => [
                        'date' => $a->date->toDateString(),
                        'workcenter_name' => $a->workcenter->name,
                        'shift_name' => $a->shift->name,
                        'start_time' => $a->shift->start_time,
                        'end_time' => $a->shift->end_time,
                        'published' => $isPublished($a),
                    ])->values()->all(),
                ];
            })
            ->sortBy('weekStart')
            ->values()
            ->all();
    }
}
