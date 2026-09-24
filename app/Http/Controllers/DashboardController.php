<?php

namespace App\Http\Controllers;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\PlanningSettings;
use App\Models\ShiftAssignment;
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
            return Inertia::render('Dashboard/Index', [
                'period' => null,
            ]);
        }

        /** @var Collection<int, CarbonInterface> $days */
        $days = collect(CarbonPeriod::create($settings->period_start, $settings->period_end)->toArray())
            ->reject(fn (CarbonInterface $day) => $day->isWeekend())
            ->values();

        // One FTE is fte_hours per week, so fte_hours / 5 per working day.
        $dailyFteHours = $settings->fte_hours / 5;

        $businessLines = BusinessLine::all(); // position-ordered by the model scope
        $zeros = array_fill(0, $days->count(), 0.0);

        $series = [
            'confirmed' => [
                'overall' => $zeros,
                'lines' => $businessLines->mapWithKeys(fn (BusinessLine $line) => [$line->id => $zeros])->all(),
            ],
            'unconfirmed' => [
                'overall' => $zeros,
                'lines' => $businessLines->mapWithKeys(fn (BusinessLine $line) => [$line->id => $zeros])->all(),
            ],
        ];

        Employee::query()->active()->with('holidays')->get()->each(function (Employee $employee) use ($days, $settings, &$series) {
            $status = $employee->confirmed ? 'confirmed' : 'unconfirmed';

            foreach ($this->availableFte($employee, $days, $settings->fte_hours) as $i => $value) {
                $series[$status]['overall'][$i] += $value;
                if ($employee->business_line_id && isset($series[$status]['lines'][$employee->business_line_id])) {
                    $series[$status]['lines'][$employee->business_line_id][$i] += $value;
                }
            }
        });

        $planned = $this->plannedFte($days, $businessLines, $settings->fte_hours);

        return Inertia::render('Dashboard/Index', [
            'period' => [
                'start' => $settings->period_start->toDateString(),
                'end' => $settings->period_end->toDateString(),
                'fte_hours' => $settings->fte_hours,
            ],
            'days' => $days->map(fn ($day) => $day->toDateString())->all(),
            'overall' => [
                'available_confirmed' => $series['confirmed']['overall'],
                'available_unconfirmed' => $series['unconfirmed']['overall'],
                'available_total' => $this->sumSeries($series['confirmed']['overall'], $series['unconfirmed']['overall']),
                'planned' => $planned['overall'],
                'target' => (float) $businessLines->sum('target_fte'),
                'available_hours_confirmed' => $this->availableHours($series['confirmed']['overall'], $dailyFteHours),
                'available_hours_unconfirmed' => $this->availableHours($series['unconfirmed']['overall'], $dailyFteHours),
                'required_hours' => (float) $businessLines->sum('target_fte') * $days->count() * $dailyFteHours,
            ],
            'lines' => $businessLines->map(function (BusinessLine $line) use ($days, $dailyFteHours, $series, $planned) {
                $confirmed = $series['confirmed']['lines'][$line->id];
                $unconfirmed = $series['unconfirmed']['lines'][$line->id];

                return [
                    'id' => $line->id,
                    'abbreviation' => $line->abbreviation,
                    'description' => $line->description,
                    'available_confirmed' => $confirmed,
                    'available_unconfirmed' => $unconfirmed,
                    'available_total' => $this->sumSeries($confirmed, $unconfirmed),
                    'planned' => $planned['lines'][$line->id],
                    'target' => (float) $line->target_fte,
                    'available_hours_confirmed' => $this->availableHours($confirmed, $dailyFteHours),
                    'available_hours_unconfirmed' => $this->availableHours($unconfirmed, $dailyFteHours),
                    'required_hours' => (float) $line->target_fte * $days->count() * $dailyFteHours,
                ];
            })->all(),
        ]);
    }

    /**
     * @param  list<float>  $confirmed
     * @param  list<float>  $unconfirmed
     * @return list<float>
     */
    private function sumSeries(array $confirmed, array $unconfirmed): array
    {
        return array_map(fn (float $confirmedValue, float $unconfirmedValue) => $confirmedValue + $unconfirmedValue, $confirmed, $unconfirmed);
    }

    /**
     * @param  list<float>  $series
     */
    private function availableHours(array $series, float $dailyFteHours): float
    {
        return array_sum($series) * $dailyFteHours;
    }

    /**
     * Planned FTE per day: the hours of every assignment (published or draft) in the day's
     * full Monday–Sunday week, over fte_hours. Business-line series follow the employee.
     *
     * @param  Collection<int, CarbonInterface>  $days
     * @param  Collection<int, BusinessLine>  $businessLines
     * @return array{overall: list<float>, lines: array<int, list<float>>}
     */
    private function plannedFte(Collection $days, Collection $businessLines, int $fteHours): array
    {
        $weekKey = fn (CarbonInterface $date) => $date->copy()->startOfWeek(CarbonInterface::MONDAY)->toDateString();
        $weekHours = ['overall' => [], 'lines' => []];

        if ($days->isNotEmpty()) {
            ShiftAssignment::query()
                ->whereBetween('date', [
                    $days->first()->copy()->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
                    $days->last()->copy()->endOfWeek(CarbonInterface::SUNDAY)->toDateString(),
                ])
                ->with(['employee:id,business_line_id', 'shift:id,start_time,end_time'])
                ->get()
                ->each(function (ShiftAssignment $assignment) use ($weekKey, &$weekHours) {
                    $week = $weekKey($assignment->date);
                    $hours = $assignment->shift->durationHours();
                    $weekHours['overall'][$week] = ($weekHours['overall'][$week] ?? 0.0) + $hours;

                    $lineId = $assignment->employee?->business_line_id;
                    if ($lineId) {
                        $weekHours['lines'][$lineId][$week] = ($weekHours['lines'][$lineId][$week] ?? 0.0) + $hours;
                    }
                });
        }

        $series = fn (array $hoursByWeek) => $days
            ->map(fn (CarbonInterface $day) => ($hoursByWeek[$weekKey($day)] ?? 0.0) / $fteHours)
            ->all();

        return [
            'overall' => $series($weekHours['overall']),
            'lines' => $businessLines
                ->mapWithKeys(fn (BusinessLine $line) => [$line->id => $series($weekHours['lines'][$line->id] ?? [])])
                ->all(),
        ];
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
