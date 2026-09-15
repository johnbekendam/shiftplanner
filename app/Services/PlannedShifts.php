<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PublishedWeek;
use App\Models\ShiftAssignment;
use Carbon\Carbon;

/**
 * One employee's shift assignments grouped by week, for the Planning tabs
 * on both Personal/Show.vue (published only) and Employees/Form.vue
 * (everything, each week marked published or not).
 */
class PlannedShifts
{
    /** @return array<int, array{weekStart: string, weekEnd: string, published: bool, assignments: array}> */
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

        $publishedWeekStarts = PublishedWeek::query()
            ->whereIn('week_start', $assignments->map($weekKey)->unique()->values())
            ->pluck('week_start')
            ->map(fn (Carbon $date) => $date->toDateString());

        return $assignments->groupBy($weekKey)
            ->filter(fn ($group, string $weekStart) => ! $publishedOnly || $publishedWeekStarts->contains($weekStart))
            ->map(function ($group, string $weekStart) use ($publishedWeekStarts) {
                $start = Carbon::parse($weekStart);

                return [
                    'weekStart' => $start->toDateString(),
                    'weekEnd' => $start->copy()->addDays(6)->toDateString(),
                    'published' => $publishedWeekStarts->contains($weekStart),
                    'assignments' => $group->map(fn (ShiftAssignment $a) => [
                        'date' => $a->date->toDateString(),
                        'workcenter_name' => $a->workcenter->name,
                        'shift_name' => $a->shift->name,
                        'start_time' => $a->shift->start_time,
                        'end_time' => $a->shift->end_time,
                    ])->values()->all(),
                ];
            })
            ->sortBy('weekStart')
            ->values()
            ->all();
    }
}
