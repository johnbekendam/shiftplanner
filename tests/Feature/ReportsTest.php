<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_manager_cannot_open_reports(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/reports')->assertForbidden();
    }

    public function test_without_a_shift_employees_with_no_availability_at_all_appear(): void
    {
        $this->admin();
        Shift::factory()->create();
        $employee = Employee::factory()->create(['weekly_hours' => 32, 'confirmed' => true]);

        $this->get('/reports')->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('employees.0.id', $employee->id)
        );
    }

    public function test_without_a_shift_an_employee_with_a_row_for_any_shift_does_not_appear(): void
    {
        $this->admin();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['weekly_hours' => 32, 'confirmed' => true]);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);

        $this->get('/reports')->assertInertia(fn ($page) => $page
            ->where('employees', [])
        );
    }

    public function test_employee_with_no_availability_row_for_the_shift_appears(): void
    {
        $this->admin();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['weekly_hours' => 32, 'confirmed' => true]);

        $this->get("/reports?shift={$shift->id}")->assertInertia(fn ($page) => $page
            ->where('employees.0.id', $employee->id)
        );
    }

    public function test_employee_with_an_availability_row_for_the_shift_does_not_appear(): void
    {
        $this->admin();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['weekly_hours' => 32, 'confirmed' => true]);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);

        $this->get("/reports?shift={$shift->id}")->assertInertia(fn ($page) => $page
            ->where('employees', [])
        );
    }

    public function test_employee_with_zero_weekly_hours_does_not_appear(): void
    {
        $this->admin();
        $shift = Shift::factory()->create();
        Employee::factory()->create(['weekly_hours' => 0, 'confirmed' => true]);

        $this->get("/reports?shift={$shift->id}")->assertInertia(fn ($page) => $page
            ->where('employees', [])
        );
    }

    public function test_unconfirmed_employee_is_excluded_by_default(): void
    {
        $this->admin();
        $shift = Shift::factory()->create();
        Employee::factory()->create(['weekly_hours' => 32, 'confirmed' => false]);

        $this->get("/reports?shift={$shift->id}")->assertInertia(fn ($page) => $page
            ->where('employees', [])
        );
    }

    public function test_unconfirmed_employee_appears_when_toggle_is_on(): void
    {
        $this->admin();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['weekly_hours' => 32, 'confirmed' => false]);

        $this->get("/reports?shift={$shift->id}&unconfirmed=1")->assertInertia(fn ($page) => $page
            ->where('employees.0.id', $employee->id)
        );
    }

    public function test_business_line_filter_narrows_the_list(): void
    {
        $this->admin();
        $shift = Shift::factory()->create();
        $lineA = BusinessLine::factory()->create();
        $lineB = BusinessLine::factory()->create();
        $employeeA = Employee::factory()->create([
            'weekly_hours' => 32, 'confirmed' => true, 'business_line_id' => $lineA->id,
        ]);
        Employee::factory()->create([
            'weekly_hours' => 32, 'confirmed' => true, 'business_line_id' => $lineB->id,
        ]);

        $this->get("/reports?shift={$shift->id}&business_line={$lineA->id}")->assertInertia(fn ($page) => $page
            ->where('employees.0.id', $employeeA->id)
            ->has('employees', 1)
        );
    }
}
