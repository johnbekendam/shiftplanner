<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\PlanningSettings;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePlanningRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_visibility_inherits_the_live_shift_default(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create(['visible_by_default' => true]);

        $this->assertTrue($shift->visible_by_default);

        $shift->update(['visible_by_default' => false]);

        $this->assertFalse($shift->fresh()->visible_by_default);
    }

    public function test_weekly_hours_minimum_inherits_the_global_value_or_uses_an_override(): void
    {
        PlanningSettings::current()->update(['weekly_hours_minimum' => 20]);
        $employee = Employee::factory()->create(['weekly_hours_minimum' => null]);

        $this->assertSame(20, $employee->effectiveWeeklyHoursMinimum());

        $employee->update(['weekly_hours_minimum' => 28]);

        $this->assertSame(28, $employee->effectiveWeeklyHoursMinimum());
    }

}