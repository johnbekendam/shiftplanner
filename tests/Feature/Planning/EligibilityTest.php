<?php

namespace Tests\Feature\Planning;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\ShiftAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EligibilityTest extends TestCase
{
    use BuildsPlanningScenarios;
    use RefreshDatabase;

    public function test_ignores_an_employee_who_is_not_confirmed(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = Employee::factory()->create(['confirmed' => false, 'weekly_hours' => 40]);
        $this->makeAvailable($employee, $shift, '2026-09-08');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $this->assertSame('no_eligible_employee', $run->refresh()->unfulfilled[0]['reason']);
    }

    public function test_ignores_an_employee_with_zero_weekly_hours(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = Employee::factory()->create(['confirmed' => true, 'weekly_hours' => 0]);
        $this->makeAvailable($employee, $shift, '2026-09-08');

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
    }

    public function test_a_holiday_makes_the_only_candidate_unavailable(): void
    {
        [, $shift] = $this->workcenterWithOverride('2026-09-08');
        $employee = $this->eligibleEmployee($shift, '2026-09-08');
        EmployeeHoliday::query()->create([
            'employee_id' => $employee->id, 'start_date' => '2026-09-08', 'end_date' => '2026-09-08',
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(0, ShiftAssignment::count());
        $unfulfilled = $run->refresh()->unfulfilled;
        $this->assertCount(1, $unfulfilled);
        $this->assertSame('no_eligible_employee', $unfulfilled[0]['reason']);
    }
}
