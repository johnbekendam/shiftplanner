<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EligibleEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    /** A Tuesday, so recurring_availabilities (weekday-only, Mon-Fri) can constrain it. */
    private function aTuesday(): string
    {
        return '2026-09-15';
    }

    private function url(Workcenter $workcenter, Shift $shift): string
    {
        return "/scheduling/eligible-employees?workcenter_id={$workcenter->id}&shift_id={$shift->id}&date={$this->aTuesday()}";
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_is_redirected(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->get($this->url($workcenter, $shift))->assertRedirect('/login');
    }

    public function test_manager_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create());
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->get($this->url($workcenter, $shift))->assertForbidden();
    }

    // ── Filtering ──────────────────────────────────────────────────────

    public function test_excludes_an_employee_already_assigned_to_this_cell(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id, 'date' => $this->aTuesday(),
        ]);

        $ids = collect($this->get($this->url($workcenter, $shift))->json())->pluck('id');

        $this->assertNotContains($employee->id, $ids);
    }

    public function test_excludes_an_employee_on_holiday(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();
        EmployeeHoliday::factory()->create([
            'employee_id' => $employee->id, 'start_date' => $this->aTuesday(), 'end_date' => $this->aTuesday(),
        ]);

        $ids = collect($this->get($this->url($workcenter, $shift))->json())->pluck('id');

        $this->assertNotContains($employee->id, $ids);
    }

    public function test_excludes_an_unavailable_employee(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $shift->id, 'level' => 'unavailable',
        ]);

        $ids = collect($this->get($this->url($workcenter, $shift))->json())->pluck('id');

        $this->assertNotContains($employee->id, $ids);
    }

    public function test_excludes_an_employee_when_the_shift_is_hidden(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();
        $employee->shiftVisibilityOverrides()->attach($shift, ['visible' => false]);

        $ids = collect($this->get($this->url($workcenter, $shift))->json())->pluck('id');

        $this->assertNotContains($employee->id, $ids);
    }

    public function test_excludes_an_employee_with_a_same_date_overlapping_assignment(): void
    {
        $this->actingAsAdmin();
        $employee = Employee::factory()->create();
        $otherWorkcenter = Workcenter::factory()->create();
        $overlappingShift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $otherWorkcenter->id,
            'shift_id' => $overlappingShift->id, 'date' => $this->aTuesday(),
        ]);

        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '08:00', 'end_time' => '16:00']);

        $ids = collect($this->get($this->url($workcenter, $shift))->json())->pluck('id');

        $this->assertNotContains($employee->id, $ids);
    }

    public function test_includes_a_not_preferred_employee_flagged(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id, 'weekday' => 2, 'shift_id' => $shift->id, 'level' => 'not_preferred',
        ]);

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertNotNull($entry);
        $this->assertTrue($entry['not_preferred']);
    }

    public function test_includes_an_otherwise_unconstrained_employee(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();

        $entry = collect($this->get($this->url($workcenter, $shift))->json())->firstWhere('id', $employee->id);

        $this->assertNotNull($entry);
        $this->assertFalse($entry['not_preferred']);
    }
}
