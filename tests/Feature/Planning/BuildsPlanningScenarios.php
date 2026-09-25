<?php

namespace Tests\Feature\Planning;

use App\Models\Employee;
use App\Models\PlanGenerationRun;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftDateOverride;
use App\Services\Planning\HeuristicPlanGenerator;
use Carbon\Carbon;

/**
 * Shared scenario builders for the planner tests. The app treats a missing
 * availability row as unavailable, so every candidate needs an explicit row.
 */
trait BuildsPlanningScenarios
{
    protected const CYCLE_START = '2026-09-07'; // a Monday

    /** @var array<int, int[]> employee_id => workcenter ids granted by grantWorkcenters() */
    private array $grantedWorkcenters = [];

    protected function generator(): HeuristicPlanGenerator
    {
        $this->grantWorkcenters();

        return app(HeuristicPlanGenerator::class);
    }

    /**
     * Makes each employee() member of every workcenter, so a scenario does
     * not have to set up memberships. An employee whose rows the test
     * changed itself keeps those rows.
     */
    private function grantWorkcenters(): void
    {
        $all = Workcenter::pluck('id')->sort()->values()->all();

        foreach ($this->grantedWorkcenters as $employeeId => $granted) {
            $employee = Employee::find($employeeId);
            if ($employee === null) {
                continue;
            }

            $current = $employee->workcenters()->pluck('workcenters.id')->sort()->values()->all();
            if ($current !== $granted) {
                continue;
            }

            $employee->workcenters()->syncWithoutDetaching($all);
            $this->grantedWorkcenters[$employeeId] = $all;
        }
    }

    protected function makeRun(string $cycleStart = self::CYCLE_START): PlanGenerationRun
    {
        return PlanGenerationRun::create(['cycle_start' => $cycleStart, 'status' => PlanGenerationRun::STATUS_PENDING]);
    }

    protected function employee(array $overrides = []): Employee
    {
        $employee = Employee::factory()->create(array_merge(['confirmed' => true, 'weekly_hours' => 40], $overrides));
        $this->grantedWorkcenters[$employee->id] = [];

        return $employee;
    }

    protected function makeAvailable(Employee $employee, Shift $shift, string $date, string $level = 'available'): void
    {
        RecurringAvailability::query()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'weekday' => Carbon::parse($date)->isoWeekday(),
            'level' => $level,
        ]);
    }

    /** A confirmed employee explicitly available for $shift on $date's weekday. */
    protected function eligibleEmployee(Shift $shift, string $date): Employee
    {
        $employee = $this->employee();
        $this->makeAvailable($employee, $shift, $date);

        return $employee;
    }

    /** Attaches $shift to $workcenter and opens $spots spots on $date. */
    protected function openCell(Workcenter $workcenter, Shift $shift, string $date, int $spots = 1): void
    {
        $workcenter->shifts()->attach($shift, []);
        $this->openDay($workcenter, $shift, $date, $spots);
    }

    /** Opens $spots more spots on $date for a shift that is already attached to $workcenter. */
    protected function openDay(Workcenter $workcenter, Shift $shift, string $date, int $spots = 1): void
    {
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date, 'spots' => $spots,
        ]);
    }

    /** A new workcenter with a new 06:00-14:00 shift and $spots open spots on $date. */
    protected function workcenterWithOverride(string $date, int $spots = 1): array
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->openCell($workcenter, $shift, $date, $spots);

        return [$workcenter, $shift];
    }
}
