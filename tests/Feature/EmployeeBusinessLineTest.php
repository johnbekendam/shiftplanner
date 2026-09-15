<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeBusinessLineTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_the_assigned_business_line_abbreviation(): void
    {
        $user = User::factory()->create();
        $line = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        Employee::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Able', 'business_line_id' => $line->id]);
        Employee::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Zeal', 'business_line_id' => null]);

        $this->actingAs($user)->get('/employees')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('employees.data.0.business_line', 'PMP')
                ->where('employees.data.1.business_line', null)
                ->missing('employees.data.0.email')
            );
    }

    public function test_index_can_filter_to_one_business_line(): void
    {
        $user = User::factory()->create();
        $pmp = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        $vlv = BusinessLine::factory()->create(['abbreviation' => 'VLV']);
        Employee::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Able', 'business_line_id' => $pmp->id]);
        Employee::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Zeal', 'business_line_id' => $vlv->id]);

        $this->actingAs($user)->get("/employees?business_lines[]={$pmp->id}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('employees.data', 1)
                ->where('employees.data.0.name', 'Aaron Able')
                ->where('selectedBusinessLines', [$pmp->id])
            );
    }

    public function test_index_can_filter_to_employees_with_no_business_line(): void
    {
        $user = User::factory()->create();
        $pmp = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        Employee::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Able', 'business_line_id' => $pmp->id]);
        Employee::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Zeal', 'business_line_id' => null]);

        $this->actingAs($user)->get('/employees?business_lines[]=none')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('employees.data', 1)
                ->where('employees.data.0.name', 'Zoe Zeal')
                ->where('selectedBusinessLines', ['none'])
            );
    }

    public function test_index_can_combine_multiple_business_line_filters(): void
    {
        $user = User::factory()->create();
        $pmp = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        $vlv = BusinessLine::factory()->create(['abbreviation' => 'VLV']);
        Employee::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Able', 'business_line_id' => $pmp->id]);
        Employee::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Zeal', 'business_line_id' => $vlv->id]);
        Employee::factory()->create(['first_name' => 'Bo', 'last_name' => 'Bell', 'business_line_id' => null]);

        $this->actingAs($user)->get("/employees?business_lines[]={$pmp->id}&business_lines[]=none")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('employees.data', 2)
                ->where('employees.data.0.name', 'Aaron Able')
                ->where('employees.data.1.name', 'Bo Bell')
            );
    }

    public function test_index_ignores_an_unknown_business_line_id(): void
    {
        $user = User::factory()->create();
        $pmp = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        Employee::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Able', 'business_line_id' => $pmp->id]);

        $this->actingAs($user)->get("/employees?business_lines[]={$pmp->id}&business_lines[]=999")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('employees.data', 1)
                ->where('employees.data.0.name', 'Aaron Able')
            );
    }

    public function test_index_with_no_business_line_param_shows_everyone_and_selects_all(): void
    {
        $user = User::factory()->create();
        $pmp = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        Employee::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Able', 'business_line_id' => $pmp->id]);
        Employee::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Zeal', 'business_line_id' => null]);

        $this->actingAs($user)->get('/employees')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('employees.data', 2)
                ->where('selectedBusinessLines', [$pmp->id, 'none'])
                ->has('businessLines', 1)
                ->where('businessLines.0.abbreviation', 'PMP')
            );
    }

    public function test_index_can_sort_by_name(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Zeal']);
        Employee::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Able']);

        $this->actingAs($user)->get('/employees?sort=name&direction=asc')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sort', 'name')
                ->where('direction', 'asc')
                ->where('employees.data.0.name', 'Aaron Able')
                ->where('employees.data.1.name', 'Zoe Zeal')
            );
    }

    public function test_index_can_sort_by_business_line(): void
    {
        $user = User::factory()->create();
        $pmp = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        $vlv = BusinessLine::factory()->create(['abbreviation' => 'VLV']);
        Employee::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Able', 'business_line_id' => $vlv->id]);
        Employee::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Zeal', 'business_line_id' => $pmp->id]);

        $this->actingAs($user)->get('/employees?sort=business_line&direction=asc')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sort', 'business_line')
                ->where('direction', 'asc')
                ->where('employees.data.0.name', 'Zoe Zeal')
                ->where('employees.data.1.name', 'Aaron Able')
            );
    }

    public function test_index_can_sort_by_weekly_hours(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Zeal', 'weekly_hours' => 40]);
        Employee::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Able', 'weekly_hours' => 24]);

        $this->actingAs($user)->get('/employees?sort=weekly_hours&direction=asc')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sort', 'weekly_hours')
                ->where('direction', 'asc')
                ->where('employees.data.0.name', 'Aaron Able')
                ->where('employees.data.1.name', 'Zoe Zeal')
            );
    }

    public function test_store_persists_the_business_line(): void
    {
        $user = User::factory()->create();
        $line = BusinessLine::factory()->create();

        $this->actingAs($user)->post('/employees', [
            'first_name' => 'New', 'last_name' => 'Hire',
            'email' => 'new.hire@example.com',
            'weekly_hours' => 32,
            'business_line_id' => $line->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'email' => 'new.hire@example.com',
            'business_line_id' => $line->id,
        ]);
    }

    public function test_store_allows_no_business_line(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/employees', [
            'first_name' => 'New', 'last_name' => 'Hire',
            'email' => 'new.hire@example.com',
            'weekly_hours' => 32,
            'business_line_id' => null,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'email' => 'new.hire@example.com',
            'business_line_id' => null,
        ]);
    }

    public function test_an_unknown_business_line_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/employees', [
            'first_name' => 'New', 'last_name' => 'Hire',
            'email' => 'new.hire@example.com',
            'weekly_hours' => 32,
            'business_line_id' => 999,
        ])->assertSessionHasErrors('business_line_id');

        $this->assertSame(0, Employee::count());
    }

    public function test_update_can_change_and_clear_the_business_line(): void
    {
        $user = User::factory()->create();
        $line = BusinessLine::factory()->create();
        $employee = Employee::factory()->create(['business_line_id' => $line->id]);

        $this->actingAs($user)->put("/employees/{$employee->id}", [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'weekly_hours' => $employee->weekly_hours,
            'business_line_id' => null,
        ])->assertRedirect("/employees/{$employee->id}/edit")->assertSessionHasNoErrors();

        $this->assertNull($employee->fresh()->business_line_id);
    }

    public function test_create_and_edit_payloads_carry_the_business_lines(): void
    {
        $user = User::factory()->create();
        $second = BusinessLine::factory()->create(['abbreviation' => 'VLV', 'position' => 2]);
        $first = BusinessLine::factory()->create(['abbreviation' => 'PMP', 'position' => 1]);
        $employee = Employee::factory()->create(['business_line_id' => $second->id]);

        $this->actingAs($user)->get('/employees/create')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('businessLines', 2)
                ->where('businessLines.0.abbreviation', 'PMP')
                ->where('businessLines.1.abbreviation', 'VLV')
            );

        $this->actingAs($user)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('businessLines', 2)
                ->where('employee.business_line_id', $second->id)
            );
    }
}
