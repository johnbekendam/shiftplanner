<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeEffectiveShiftsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_workcenter_row_returns_shifts_visible_by_default(): void
    {
        $employee = Employee::factory()->create();
        $visible = Shift::factory()->create(['visible_by_default' => true]);
        Shift::factory()->create(['visible_by_default' => false]);

        $this->assertSame([$visible->id], $employee->effectiveShifts()->pluck('id')->all());
    }

    public function test_soft_only_row_returns_shifts_visible_by_default(): void
    {
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();
        $visible = Shift::factory()->create(['visible_by_default' => true]);
        $hidden = Shift::factory()->create(['visible_by_default' => false]);
        $workcenter->shifts()->attach([$visible->id, $hidden->id]);
        $employee->workcenters()->attach($workcenter, ['mode' => 'soft']);

        $this->assertSame([$visible->id], $employee->effectiveShifts()->pluck('id')->all());
    }

    public function test_hard_row_returns_that_workcenters_shifts_including_a_hidden_one(): void
    {
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();
        $other = Workcenter::factory()->create();
        $run = Shift::factory()->create(['visible_by_default' => false]);
        $notRun = Shift::factory()->create(['visible_by_default' => true]);
        $otherWorkcenterShift = Shift::factory()->create(['visible_by_default' => true]);
        $workcenter->shifts()->attach($run);
        $other->shifts()->attach($otherWorkcenterShift);
        $employee->workcenters()->attach($workcenter, ['mode' => 'hard']);

        $this->assertSame([$run->id], $employee->effectiveShifts()->pluck('id')->all());
    }

    public function test_two_hard_rows_union_their_shifts_without_duplicates(): void
    {
        $employee = Employee::factory()->create();
        $workcenterA = Workcenter::factory()->create();
        $workcenterB = Workcenter::factory()->create();
        $shared = Shift::factory()->create(['visible_by_default' => false]);
        $onlyA = Shift::factory()->create(['visible_by_default' => false]);
        $onlyB = Shift::factory()->create(['visible_by_default' => false]);
        $workcenterA->shifts()->attach([$shared->id, $onlyA->id]);
        $workcenterB->shifts()->attach([$shared->id, $onlyB->id]);
        $employee->workcenters()->attach($workcenterA, ['mode' => 'hard']);
        $employee->workcenters()->attach($workcenterB, ['mode' => 'hard']);

        $this->assertSame(
            [$shared->id, $onlyA->id, $onlyB->id],
            $employee->effectiveShifts()->pluck('id')->sort()->values()->all()
        );
    }
}
