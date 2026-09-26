<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Shift;
use App\Services\EmployeeAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_weekly_row_is_unavailable(): void
    {
        $availability = new EmployeeAvailability(holidays: [], weekly: []);

        $this->assertSame('unavailable', $availability->status('2026-10-05', 1));
    }

    public function test_the_weekly_row_of_the_iso_weekday_decides(): void
    {
        $availability = new EmployeeAvailability(holidays: [], weekly: ['1:7' => 'available', '2:7' => 'not_preferred']);

        $this->assertSame('available', $availability->status('2026-10-05', 7)); // Monday
        $this->assertSame('not_preferred', $availability->status('2026-10-06', 7)); // Tuesday
        $this->assertSame('unavailable', $availability->status('2026-10-07', 7)); // Wednesday
    }

    public function test_a_holiday_wins_over_the_weekly_row(): void
    {
        $availability = new EmployeeAvailability(
            holidays: [['start' => '2026-10-05', 'end' => '2026-10-06']],
            weekly: ['1:7' => 'available', '3:7' => 'available'],
        );

        $this->assertSame('holiday', $availability->status('2026-10-05', 7));
        $this->assertSame('available', $availability->status('2026-10-07', 7));
    }

    public function test_a_start_date_blocks_the_days_before_it_and_wins_over_a_holiday(): void
    {
        $availability = new EmployeeAvailability(
            holidays: [['start' => '2026-10-01', 'end' => '2026-10-31']],
            weekly: ['1:7' => 'available', '2:7' => 'available'],
            availableFrom: '2026-10-06',
        );

        $this->assertSame('not_started', $availability->status('2026-10-05', 7));
        $this->assertSame('holiday', $availability->status('2026-10-06', 7));
    }

    public function test_is_assignable_accepts_available_and_not_preferred_only(): void
    {
        $this->assertTrue(EmployeeAvailability::isAssignable('available'));
        $this->assertTrue(EmployeeAvailability::isAssignable('not_preferred'));
        $this->assertFalse(EmployeeAvailability::isAssignable('unavailable'));
        $this->assertFalse(EmployeeAvailability::isAssignable('holiday'));
    }

    public function test_from_employee_reads_the_relations(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();
        $employee->recurringAvailabilities()->create(['weekday' => 1, 'shift_id' => $shift->id, 'level' => 'available']);
        $employee->holidays()->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-12']);

        $availability = EmployeeAvailability::fromEmployee($employee);

        $this->assertSame('available', $availability->status('2026-10-05', $shift->id));
        $this->assertSame('holiday', $availability->status('2026-10-12', $shift->id));
    }

    public function test_from_plan_employee_reads_the_problem_arrays(): void
    {
        $availability = EmployeeAvailability::fromPlanEmployee([
            'holidays' => [['start' => '2026-10-12', 'end' => '2026-10-12']],
            'recurring_availability' => [['weekday' => 1, 'shift_id' => 7, 'level' => 'not_preferred']],
        ]);

        $this->assertSame('not_preferred', $availability->status('2026-10-05', 7));
        $this->assertSame('holiday', $availability->status('2026-10-12', 7));
    }
}
