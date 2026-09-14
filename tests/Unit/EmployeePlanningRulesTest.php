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

        $this->assertTrue($employee->isShiftVisible($shift));

        $shift->update(['visible_by_default' => false]);

        $this->assertFalse($employee->isShiftVisible($shift->fresh()));
    }

    public function test_explicit_shift_visibility_overrides_the_shift_default(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create(['visible_by_default' => true]);
        $employee->shiftVisibilityOverrides()->attach($shift, ['visible' => false]);

        $this->assertFalse($employee->isShiftVisible($shift));

        $shift->update(['visible_by_default' => false]);
        $employee->shiftVisibilityOverrides()->updateExistingPivot($shift, ['visible' => true]);

        $this->assertTrue($employee->isShiftVisible($shift->fresh()));
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