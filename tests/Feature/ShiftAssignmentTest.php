<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    /** A Tuesday, so recurring_availabilities (weekday-only, Mon-Fri) can constrain it. */
    private function aTuesday(): Carbon
    {
        return Carbon::parse('2026-09-15');
    }

    private function setCapacity(Workcenter $workcenter, Shift $shift, Carbon $date, int $spots): void
    {
        $workcenter->shifts()->attach($shift);
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'weekday' => $date->isoWeekday(),
            'spots' => $spots,
        ]);
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_cannot_create_an_assignment(): void
    {
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $this->setCapacity($workcenter, $shift, $this->aTuesday(), 1);

        $this->post('/scheduling/assignments', $this->validPayload($employee, $workcenter, $shift))
            ->assertRedirect('/login');
        $this->assertSame(0, ShiftAssignment::count());
    }

    public function test_manager_cannot_create_an_assignment(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $this->setCapacity($workcenter, $shift, $this->aTuesday(), 1);

        $this->post('/scheduling/assignments', $this->validPayload($employee, $workcenter, $shift))
            ->assertForbidden();
    }

    // ── Create ─────────────────────────────────────────────────────────

    public function test_store_creates_an_assignment(): void
    {
        $this->actingAsAdmin();
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $this->setCapacity($workcenter, $shift, $this->aTuesday(), 1);

        $this->post('/scheduling/assignments', $this->validPayload($employee, $workcenter, $shift))
            ->assertRedirect();

        $this->assertDatabaseHas('shift_assignments', [
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => $this->aTuesday()->toDateString(),
            'fixed' => false,
        ]);
    }

    public function test_store_rejects_a_full_cell(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $this->setCapacity($workcenter, $shift, $this->aTuesday(), 1);
        ShiftAssignment::factory()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $this->aTuesday(),
        ]);
        $employee = Employee::factory()->create();

        $this->post('/scheduling/assignments', $this->validPayload($employee, $workcenter, $shift))
            ->assertSessionHasErrors('employee_id');
        $this->assertSame(1, ShiftAssignment::count());
    }

    public function test_store_rejects_a_duplicate_pair(): void
    {
        $this->actingAsAdmin();
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $this->setCapacity($workcenter, $shift, $this->aTuesday(), 5);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id, 'date' => $this->aTuesday(),
        ]);

        $this->post('/scheduling/assignments', $this->validPayload($employee, $workcenter, $shift))
            ->assertSessionHasErrors('employee_id');
        $this->assertSame(1, ShiftAssignment::count());
    }

    public function test_store_rejects_an_employee_on_holiday(): void
    {
        $this->actingAsAdmin();
        $employee = Employee::factory()->create();
        EmployeeHoliday::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => $this->aTuesday()->toDateString(),
            'end_date' => $this->aTuesday()->toDateString(),
        ]);
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $this->setCapacity($workcenter, $shift, $this->aTuesday(), 5);

        $this->post('/scheduling/assignments', $this->validPayload($employee, $workcenter, $shift))
            ->assertSessionHasErrors('employee_id');
        $this->assertSame(0, ShiftAssignment::count());
    }

    public function test_store_rejects_an_unavailable_employee(): void
    {
        $this->actingAsAdmin();
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $this->setCapacity($workcenter, $shift, $this->aTuesday(), 5);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id,
            'weekday' => $this->aTuesday()->isoWeekday(),
            'shift_id' => $shift->id,
            'level' => 'unavailable',
        ]);

        $this->post('/scheduling/assignments', $this->validPayload($employee, $workcenter, $shift))
            ->assertSessionHasErrors('employee_id');
        $this->assertSame(0, ShiftAssignment::count());
    }

    public function test_store_accepts_a_not_preferred_employee(): void
    {
        $this->actingAsAdmin();
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $this->setCapacity($workcenter, $shift, $this->aTuesday(), 5);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id,
            'weekday' => $this->aTuesday()->isoWeekday(),
            'shift_id' => $shift->id,
            'level' => 'not_preferred',
        ]);

        $this->post('/scheduling/assignments', $this->validPayload($employee, $workcenter, $shift))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ShiftAssignment::count());
    }

    public function test_store_rejects_a_same_date_overlapping_assignment(): void
    {
        $this->actingAsAdmin();
        $employee = Employee::factory()->create();
        $workcenterA = Workcenter::factory()->create();
        $shiftA = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->setCapacity($workcenterA, $shiftA, $this->aTuesday(), 5);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenterA->id,
            'shift_id' => $shiftA->id, 'date' => $this->aTuesday(),
        ]);

        $workcenterB = Workcenter::factory()->create();
        $shiftB = Shift::factory()->create(['start_time' => '08:00', 'end_time' => '16:00']);
        $this->setCapacity($workcenterB, $shiftB, $this->aTuesday(), 5);

        $this->post('/scheduling/assignments', $this->validPayload($employee, $workcenterB, $shiftB))
            ->assertSessionHasErrors('employee_id');
        $this->assertSame(1, ShiftAssignment::count());
    }

    public function test_store_accepts_a_non_overlapping_shift_the_same_day(): void
    {
        $this->actingAsAdmin();
        $employee = Employee::factory()->create();
        $workcenterA = Workcenter::factory()->create();
        $shiftA = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->setCapacity($workcenterA, $shiftA, $this->aTuesday(), 5);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenterA->id,
            'shift_id' => $shiftA->id, 'date' => $this->aTuesday(),
        ]);

        $workcenterB = Workcenter::factory()->create();
        $shiftB = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $this->setCapacity($workcenterB, $shiftB, $this->aTuesday(), 5);

        $this->post('/scheduling/assignments', $this->validPayload($employee, $workcenterB, $shiftB))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, ShiftAssignment::count());
    }

    // ── Update (fixed) ────────────────────────────────────────────────

    public function test_update_toggles_fixed(): void
    {
        $this->actingAsAdmin();
        $assignment = ShiftAssignment::factory()->create(['fixed' => false]);

        $this->put("/scheduling/assignments/{$assignment->id}", ['fixed' => true])->assertRedirect();

        $this->assertTrue($assignment->fresh()->fixed);
    }

    public function test_guest_cannot_update_fixed(): void
    {
        $assignment = ShiftAssignment::factory()->create(['fixed' => false]);

        $this->put("/scheduling/assignments/{$assignment->id}", ['fixed' => true])->assertRedirect('/login');
        $this->assertFalse($assignment->fresh()->fixed);
    }

    // ── Delete ─────────────────────────────────────────────────────────

    public function test_destroy_removes_a_fixed_assignment_with_no_extra_step(): void
    {
        $this->actingAsAdmin();
        $assignment = ShiftAssignment::factory()->create(['fixed' => true]);

        $this->delete("/scheduling/assignments/{$assignment->id}")->assertRedirect();

        $this->assertDatabaseMissing('shift_assignments', ['id' => $assignment->id]);
    }

    public function test_guest_cannot_destroy_an_assignment(): void
    {
        $assignment = ShiftAssignment::factory()->create();

        $this->delete("/scheduling/assignments/{$assignment->id}")->assertRedirect('/login');
        $this->assertDatabaseHas('shift_assignments', ['id' => $assignment->id]);
    }

    /** @return array<string, mixed> */
    private function validPayload(Employee $employee, Workcenter $workcenter, Shift $shift): array
    {
        return [
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => $this->aTuesday()->toDateString(),
        ];
    }
}
