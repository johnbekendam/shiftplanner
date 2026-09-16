<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeWorkcenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_toggle_a_workcenter(): void
    {
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();

        $this->put("/employees/{$employee->id}/workcenters/{$workcenter->id}", ['mode' => 'hard'])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('employee_workcenter', 0);
    }

    public function test_manager_attaches_then_detaches_a_hard_row(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();
        $base = "/employees/{$employee->id}/workcenters/{$workcenter->id}";

        $this->actingAs($user)->put($base, ['mode' => 'hard'])->assertRedirect();
        $this->assertDatabaseHas('employee_workcenter', [
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'mode' => 'hard',
        ]);

        $this->actingAs($user)->delete($base)->assertRedirect();
        $this->assertDatabaseCount('employee_workcenter', 0);
    }

    public function test_manager_attaches_a_soft_row(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();

        $this->actingAs($user)
            ->put("/employees/{$employee->id}/workcenters/{$workcenter->id}", ['mode' => 'soft'])
            ->assertRedirect();

        $this->assertDatabaseHas('employee_workcenter', [
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'mode' => 'soft',
        ]);
    }

    public function test_updating_the_mode_changes_the_existing_row_instead_of_adding_one(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();
        $base = "/employees/{$employee->id}/workcenters/{$workcenter->id}";

        $this->actingAs($user)->put($base, ['mode' => 'hard']);
        $this->actingAs($user)->put($base, ['mode' => 'soft']);

        $this->assertDatabaseCount('employee_workcenter', 1);
        $this->assertDatabaseHas('employee_workcenter', [
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'mode' => 'soft',
        ]);
    }

    public function test_an_invalid_mode_is_rejected(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();

        $this->actingAs($user)
            ->put("/employees/{$employee->id}/workcenters/{$workcenter->id}", ['mode' => 'bogus'])
            ->assertSessionHasErrors('mode');

        $this->assertDatabaseCount('employee_workcenter', 0);
    }

    public function test_detaching_a_workcenter_the_employee_does_not_hold_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $workcenter = Workcenter::factory()->create();

        $this->actingAs($user)
            ->delete("/employees/{$employee->id}/workcenters/{$workcenter->id}")
            ->assertRedirect();

        $this->assertDatabaseCount('employee_workcenter', 0);
    }

    public function test_an_unknown_workcenter_is_not_found(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->put("/employees/{$employee->id}/workcenters/9999", ['mode' => 'hard'])
            ->assertNotFound();
    }

    public function test_edit_payload_lists_eligible_workcenters_and_the_employees_rows(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $b = Workcenter::factory()->create(['name' => 'B', 'position' => 2]);
        $a = Workcenter::factory()->create(['name' => 'A', 'position' => 1]);
        $archived = Workcenter::factory()->create(['name' => 'Old', 'position' => 3, 'archived_at' => now()]);
        $employee->workcenters()->attach($b, ['mode' => 'hard']);

        $this->actingAs($user)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Form')
                ->has('workcenters', 2)
                ->where('workcenters.0.name', 'A')
                ->where('workcenters.0.archived', false)
                ->where('workcenters.1.name', 'B')
                ->where('employeeWorkcenterAssignments', [
                    ['workcenter_id' => $b->id, 'mode' => 'hard'],
                ])
            );
    }

    public function test_edit_payload_includes_an_archived_workcenter_the_employee_already_holds(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $archived = Workcenter::factory()->create(['name' => 'Old', 'archived_at' => now()]);
        $employee->workcenters()->attach($archived, ['mode' => 'soft']);

        $this->actingAs($user)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Form')
                ->has('workcenters', 1)
                ->where('workcenters.0.name', 'Old')
                ->where('workcenters.0.archived', true)
            );
    }
}
