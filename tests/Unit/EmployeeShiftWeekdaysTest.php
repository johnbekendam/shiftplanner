<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
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

    private function dateOverride(Workcenter $workcenter, Shift $shift, string $date, int $spots): void
    {
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date, 'spots' => $spots,
        ]);
    }

    public function test_date_capacity_overrides_open_and_close_single_dates(): void
    {
        $employee = Employee::factory()->create();
        $own = Workcenter::factory()->create();
        $second = Workcenter::factory()->create();
        $other = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee->workcenters()->attach([$own->id, $second->id]);
        $this->capacity($own, $shift, 1);
        $this->capacity($second, $shift, 2);
        // Saturday 2026-10-10 opens; Monday 2026-10-05 closes at both workcenters;
        // Tuesday 2026-10-06 closes at one workcenter only, so it still runs.
        $this->dateOverride($own, $shift, '2026-10-10', 3);
        $this->dateOverride($own, $shift, '2026-10-05', 0);
        $this->dateOverride($second, $shift, '2026-10-06', 0);
        $this->capacity($own, $shift, 2);
        // Another workcenter's override does not count.
        $this->dateOverride($other, $shift, '2026-10-11', 2);

        $this->assertSame([$shift->id => ['open' => ['2026-10-10'], 'closed' => ['2026-10-05']]], $employee->shiftDateExceptions());
        $runs = fn (string $date) => $employee->shiftsRunningOn($date, [$shift->id]) === [$shift->id];
        $this->assertTrue($runs('2026-10-10'));
        $this->assertFalse($runs('2026-10-05'));
        $this->assertTrue($runs('2026-10-06'));
        $this->assertTrue($runs('2026-10-12'));
        $this->assertFalse($runs('2026-10-11'));
    }

    public function test_a_member_gets_any_active_workcenters_weekdays_for_a_default_visible_shift_their_workcenters_do_not_run(): void
    {
        $employee = Employee::factory()->create();
        $own = Workcenter::factory()->create();
        $other = Workcenter::factory()->create();
        $archived = Workcenter::factory()->create(['archived_at' => now()]);
        $ownShift = Shift::factory()->create(['visible_by_default' => false]);
        $defaultShift = Shift::factory()->create(['visible_by_default' => true]);
        $employee->workcenters()->attach($own);
        $this->capacity($own, $ownShift, 1);
        $this->capacity($other, $ownShift, 2);
        $this->capacity($other, $defaultShift, 3);
        $this->capacity($archived, $defaultShift, 5);

        $this->assertEquals([$ownShift->id => [1], $defaultShift->id => [3]], $employee->shiftWeekdays());
    }

    public function test_a_member_gets_any_active_workcenters_date_overrides_for_a_default_visible_shift_their_workcenters_do_not_run(): void
    {
        $employee = Employee::factory()->create();
        $own = Workcenter::factory()->create();
        $other = Workcenter::factory()->create();
        $ownShift = Shift::factory()->create(['visible_by_default' => false]);
        $defaultShift = Shift::factory()->create(['visible_by_default' => true]);
        $employee->workcenters()->attach($own);
        $this->capacity($own, $ownShift, 1);
        $this->capacity($other, $defaultShift, 1);
        // Saturday 2026-10-10 opens and Monday 2026-10-05 closes the default shift at the other workcenter.
        $this->dateOverride($other, $defaultShift, '2026-10-10', 2);
        $this->dateOverride($other, $defaultShift, '2026-10-05', 0);
        // The other workcenter's override does not count for a shift the employee's workcenter runs.
        $this->dateOverride($other, $ownShift, '2026-10-10', 2);

        $this->assertSame(
            [$defaultShift->id => ['open' => ['2026-10-10'], 'closed' => ['2026-10-05']]],
            $employee->shiftDateExceptions()
        );
    }
}
