<?php

namespace App\Http\Controllers;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\PlanningSettings;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $settings = PlanningSettings::current();
        $employeeStatusFilter = $this->employeeStatusFilter($request->query('employees'));

        $unconfirmedEmployeeCount = Employee::query()->where('confirmed', false)->count();

        if (! $settings->period_start || ! $settings->period_end || $settings->period_end->lt($settings->period_start)) {
            return Inertia::render('Dashboard/Index', [
                'period' => null,
                'employeeStatusFilter' => $employeeStatusFilter,
                'unconfirmedEmployeeCount' => $unconfirmedEmployeeCount,
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

        Employee::query()->with('holidays')->get()->each(function (Employee $employee) use ($days, $settings, &$series) {
            $status = $employee->confirmed ? 'confirmed' : 'unconfirmed';

            foreach ($this->availableFte($employee, $days, $settings->fte_hours) as $i => $value) {
                $series[$status]['overall'][$i] += $value;
                if ($employee->business_line_id && isset($series[$status]['lines'][$employee->business_line_id])) {
                    $series[$status]['lines'][$employee->business_line_id][$i] += $value;
                }
            }
        });

        $overall = $this->selectedSeries(
            $employeeStatusFilter,
            $series['confirmed']['overall'],
            $series['unconfirmed']['overall'],
        );

        return Inertia::render('Dashboard/Index', [
            'period' => [
                'start' => $settings->period_start->toDateString(),
                'end' => $settings->period_end->toDateString(),
                'fte_hours' => $settings->fte_hours,
            ],
            'employeeStatusFilter' => $employeeStatusFilter,
            'days' => $days->map(fn ($day) => $day->toDateString())->all(),
            'overall' => [
                'available' => $overall,
                'available_confirmed' => $series['confirmed']['overall'],
                'available_unconfirmed' => $series['unconfirmed']['overall'],
                'target' => (float) $businessLines->sum('target_fte'),
                'available_hours' => $this->availableHours($overall, $dailyFteHours),
                'available_hours_confirmed' => $this->availableHours($series['confirmed']['overall'], $dailyFteHours),
                'available_hours_unconfirmed' => $this->availableHours($series['unconfirmed']['overall'], $dailyFteHours),
                'required_hours' => (float) $businessLines->sum('target_fte') * $days->count() * $dailyFteHours,
            ],
            'unconfirmedEmployeeCount' => $unconfirmedEmployeeCount,
            'lines' => $businessLines->map(function (BusinessLine $line) use ($days, $dailyFteHours, $employeeStatusFilter, $series) {
                $confirmed = $series['confirmed']['lines'][$line->id];
                $unconfirmed = $series['unconfirmed']['lines'][$line->id];
                $available = $this->selectedSeries($employeeStatusFilter, $confirmed, $unconfirmed);

                return [
                    'id' => $line->id,
                    'abbreviation' => $line->abbreviation,
                    'description' => $line->description,
                    'available' => $available,
                    'available_confirmed' => $confirmed,
                    'available_unconfirmed' => $unconfirmed,
                    'target' => (float) $line->target_fte,
                    'available_hours' => $this->availableHours($available, $dailyFteHours),
                    'available_hours_confirmed' => $this->availableHours($confirmed, $dailyFteHours),
                    'available_hours_unconfirmed' => $this->availableHours($unconfirmed, $dailyFteHours),
                    'required_hours' => (float) $line->target_fte * $days->count() * $dailyFteHours,
                ];
            })->all(),
        ]);
    }

    private function employeeStatusFilter(mixed $value): string
    {
        return in_array($value, ['confirmed', 'unconfirmed', 'both'], true) ? $value : 'both';
    }

    /**
     * @param  list<float>  $confirmed
     * @param  list<float>  $unconfirmed
     * @return list<float>
     */
    private function selectedSeries(string $filter, array $confirmed, array $unconfirmed): array
    {
        return match ($filter) {
            'unconfirmed' => $unconfirmed,
            'both' => array_map(fn (float $confirmedValue, float $unconfirmedValue) => $confirmedValue + $unconfirmedValue, $confirmed, $unconfirmed),
            default => $confirmed,
        };
    }

    /**
     * @param  list<float>  $series
     */
    private function availableHours(array $series, float $dailyFteHours): float
    {
        return array_sum($series) * $dailyFteHours;
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
