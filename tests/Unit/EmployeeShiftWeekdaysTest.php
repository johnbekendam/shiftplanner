<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeShiftWeekdaysTest extends TestCase
{
    use RefreshDatabase;

    private function capacity(Workcenter $workcenter, Shift $shift, int $weekday, int $spots = 2): void
    {
        $workcenter->shifts()->syncWithoutDetaching($shift);
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => $weekday, 'spots' => $spots,
        ]);
    }

    public function test_a_member_gets_the_weekdays_their_workcenters_staff_the_shift(): void
    {
        $employee = Employee::factory()->create();
        $own = Workcenter::factory()->create();
        $other = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee->workcenters()->attach($own);
        $this->capacity($own, $shift, 1);
        $this->capacity($own, $shift, 6);
        $this->capacity($own, $shift, 2, spots: 0);
        $this->capacity($other, $shift, 3);

        $this->assertSame([$shift->id => [1, 6]], $employee->shiftWeekdays());
    }

    public function test_an_employee_without_a_workcenter_uses_any_active_workcenter(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create(['visible_by_default' => true]);
        $hidden = Shift::factory()->create(['visible_by_default' => false]);
        $active = Workcenter::factory()->create();
        $archived = Workcenter::factory()->create(['archived_at' => now()]);
        $this->capacity($active, $shift, 2);
        $this->capacity($archived, $shift, 4);
        $this->capacity($active, $hidden, 2);

        $this->assertSame([$shift->id => [2]], $employee->shiftWeekdays());
    }

    public function test_an_effective_shift_without_capacity_runs_on_no_day(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create(['visible_by_default' => true]);

        $this->assertSame([$shift->id => []], $employee->shiftWeekdays());
    }
}
