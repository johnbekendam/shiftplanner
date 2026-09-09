<?php

namespace App\Http\Controllers;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\PlanningSettings;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $settings = PlanningSettings::current();

        if (! $settings->period_start || ! $settings->period_end || $settings->period_end->lt($settings->period_start)) {
            return Inertia::render('Dashboard/Index', ['period' => null]);
        }

        /** @var Collection<int, CarbonInterface> $days */
        $days = collect(CarbonPeriod::create($settings->period_start, $settings->period_end)->toArray())
            ->reject(fn (CarbonInterface $day) => $day->isWeekend())
            ->values();

        // One FTE is fte_hours per week, so fte_hours / 5 per working day.
        $dailyFteHours = $settings->fte_hours / 5;

        $businessLines = BusinessLine::all(); // position-ordered by the model scope
        $zeros = array_fill(0, $days->count(), 0.0);

        $overall = $zeros;
        $lines = $businessLines->mapWithKeys(fn (BusinessLine $line) => [$line->id => $zeros])->all();

        Employee::query()->with('holidays')->get()->each(function (Employee $employee) use ($days, $settings, &$overall, &$lines) {
            foreach ($this->availableFte($employee, $days, $settings->fte_hours) as $i => $value) {
                $overall[$i] += $value;
                if ($employee->business_line_id && isset($lines[$employee->business_line_id])) {
                    $lines[$employee->business_line_id][$i] += $value;
                }
            }
        });

        return Inertia::render('Dashboard/Index', [
            'period' => [
                'start' => $settings->period_start->toDateString(),
                'end' => $settings->period_end->toDateString(),
                'fte_hours' => $settings->fte_hours,
            ],
            'days' => $days->map(fn ($day) => $day->toDateString())->all(),
            'overall' => [
                'available' => $overall,
                'target' => (float) $businessLines->sum('target_fte'),
                'available_hours' => array_sum($overall) * $dailyFteHours,
                'required_hours' => (float) $businessLines->sum('target_fte') * $days->count() * $dailyFteHours,
            ],
            'lines' => $businessLines->map(fn (BusinessLine $line) => [
                'abbreviation' => $line->abbreviation,
                'description' => $line->description,
                'available' => $lines[$line->id],
                'target' => (float) $line->target_fte,
                'available_hours' => array_sum($lines[$line->id]) * $dailyFteHours,
                'required_hours' => (float) $line->target_fte * $days->count() * $dailyFteHours,
            ])->all(),
        ]);
    }

    /**
     * The employee's available FTE for each day: 0 on a weekend, 0 inside a
     * holiday range, 0 with no weekly hours, otherwise weekly_hours / fte_hours.
     *
     * @param  Collection<int, CarbonInterface>  $days
     * @return list<float>
     */
    private function availableFte(Employee $employee, Collection $days, int $fteHours): array
    {
        $base = $employee->weekly_hours > 0 ? $employee->weekly_hours / $fteHours : 0.0;

        return $days->map(function ($day) use ($employee, $base) {
            if ($base === 0.0 || $day->isWeekend()) {
                return 0.0;
            }

            foreach ($employee->holidays as $holiday) {
                if ($day->gte($holiday->start_date) && $day->lte($holiday->end_date)) {
                    return 0.0;
                }
            }

            return $base;
        })->all();
    }
}
