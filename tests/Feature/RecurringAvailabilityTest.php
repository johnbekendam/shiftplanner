<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\RecurringAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function token(Employee $employee): string
    {
        return $employee->personalLink()->create(['token' => 'tok-'.$employee->id])->token;
    }

    // ── Manager route ──────────────────────────────────────────────────────

    public function test_guest_cannot_set_a_cell(): void
    {
        $employee = Employee::factory()->create();

        $this->put("/employees/{$employee->id}/availability/3/morning", ['level' => 'unavailable'])
            ->assertRedirect('/login');

        $this->assertSame(0, RecurringAvailability::count());
    }

    public function test_manager_sets_a_cell(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->put("/employees/{$employee->id}/availability/3/afternoon", ['level' => 'not_preferred'])
            ->assertRedirect();

        $this->assertDatabaseHas('recurring_availabilities', [
            'employee_id' => $employee->id,
            'weekday' => 3,
            'daypart' => 'afternoon',
            'level' => 'not_preferred',
        ]);
    }

    public function test_setting_a_cell_again_updates_the_same_row(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $base = "/employees/{$employee->id}/availability/3/afternoon";

        $this->actingAs($user)->put($base, ['level' => 'not_preferred']);
        $this->actingAs($user)->put($base, ['level' => 'unavailable']);

        $this->assertSame(1, RecurringAvailability::count());
        $this->assertDatabaseHas('recurring_availabilities', [
            'employee_id' => $employee->id,
            'weekday' => 3,
            'daypart' => 'afternoon',
            'level' => 'unavailable',
        ]);
    }

    public function test_setting_a_cell_to_available_deletes_the_row(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $employee->recurringAvailabilities()->create([
            'weekday' => 3,
            'daypart' => 'evening',
            'level' => 'unavailable',
        ]);

        $this->actingAs($user)
            ->put("/employees/{$employee->id}/availability/3/evening", ['level' => 'available'])
            ->assertRedirect();

        $this->assertSame(0, RecurringAvailability::count());
    }

    public function test_an_unknown_level_is_rejected(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->put("/employees/{$employee->id}/availability/3/morning", ['level' => 'maybe'])
            ->assertSessionHasErrors('level');

        $this->assertSame(0, RecurringAvailability::count());
    }

    public function test_an_out_of_range_weekday_or_daypart_is_not_found(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user)->put("/employees/{$employee->id}/availability/0/morning", ['level' => 'unavailable'])->assertNotFound();
        $this->actingAs($user)->put("/employees/{$employee->id}/availability/8/morning", ['level' => 'unavailable'])->assertNotFound();
        $this->actingAs($user)->put("/employees/{$employee->id}/availability/3/night", ['level' => 'unavailable'])->assertNotFound();

        $this->assertSame(0, RecurringAvailability::count());
    }

    public function test_edit_payload_lists_availability(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $employee->recurringAvailabilities()->create(['weekday' => 2, 'daypart' => 'morning', 'level' => 'not_preferred']);
        $employee->recurringAvailabilities()->create(['weekday' => 5, 'daypart' => 'evening', 'level' => 'unavailable']);

        $this->actingAs($user)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Form')
                ->has('availability', 2)
                ->where('availability.0.weekday', 2)
                ->where('availability.0.daypart', 'morning')
                ->where('availability.0.level', 'not_preferred')
            );
    }

    // ── Personal route ────────────────────────────────────────────────────

    public function test_employee_sets_a_cell_by_token(): void
    {
        $employee = Employee::factory()->create();
        $token = $this->token($employee);

        $this->put("/personal/{$token}/availability/4/morning", ['level' => 'unavailable'])
            ->assertRedirect("/personal/{$token}");

        $this->assertDatabaseHas('recurring_availabilities', [
            'employee_id' => $employee->id,
            'weekday' => 4,
            'daypart' => 'morning',
            'level' => 'unavailable',
        ]);
    }

    public function test_a_bad_token_is_404(): void
    {
        $this->put('/personal/not-a-token/availability/4/morning', ['level' => 'unavailable'])
            ->assertNotFound();

        $this->assertSame(0, RecurringAvailability::count());
    }

    public function test_show_payload_includes_availability(): void
    {
        $employee = Employee::factory()->create();
        $token = $this->token($employee);
        $employee->recurringAvailabilities()->create(['weekday' => 1, 'daypart' => 'afternoon', 'level' => 'not_preferred']);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personal/Show')
                ->has('availability', 1)
                ->where('availability.0.weekday', 1)
                ->where('availability.0.level', 'not_preferred')
            );
    }
}
