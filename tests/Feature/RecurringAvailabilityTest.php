<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\RecurringAvailability;
use App\Models\Shift;
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

    private function shift(string $name = 'Early'): Shift
    {
        return Shift::factory()->create([
            'name' => $name,
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);
    }

    // ── Manager route ──────────────────────────────────────────────────────

    public function test_guest_cannot_set_a_cell(): void
    {
        $employee = Employee::factory()->create();
        $shift = $this->shift();

        $this->put("/employees/{$employee->id}/availability/3/{$shift->id}", ['level' => 'unavailable'])
            ->assertRedirect('/login');

        $this->assertSame(0, RecurringAvailability::count());
    }

    public function test_manager_sets_a_cell(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $shift = $this->shift();

        $this->actingAs($user)
            ->put("/employees/{$employee->id}/availability/3/{$shift->id}", ['level' => 'not_preferred'])
            ->assertRedirect();

        $this->assertDatabaseHas('recurring_availabilities', [
            'employee_id' => $employee->id,
            'weekday' => 3,
            'shift_id' => $shift->id,
            'level' => 'not_preferred',
        ]);
    }

    public function test_setting_a_cell_again_updates_the_same_row(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $shift = $this->shift();
        $base = "/employees/{$employee->id}/availability/3/{$shift->id}";

        $this->actingAs($user)->put($base, ['level' => 'not_preferred']);
        $this->actingAs($user)->put($base, ['level' => 'unavailable']);

        $this->assertSame(1, RecurringAvailability::count());
        $this->assertDatabaseHas('recurring_availabilities', [
            'employee_id' => $employee->id,
            'weekday' => 3,
            'shift_id' => $shift->id,
            'level' => 'unavailable',
        ]);
    }

    public function test_setting_a_cell_to_available_deletes_the_row(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $shift = $this->shift();
        $employee->recurringAvailabilities()->create([
            'weekday' => 3,
            'shift_id' => $shift->id,
            'level' => 'unavailable',
        ]);

        $this->actingAs($user)
            ->put("/employees/{$employee->id}/availability/3/{$shift->id}", ['level' => 'available'])
            ->assertRedirect();

        $this->assertSame(0, RecurringAvailability::count());
    }

    public function test_deleting_a_shift_cascades_its_cells(): void
    {
        $employee = Employee::factory()->create();
        $shift = $this->shift();
        $employee->recurringAvailabilities()->create([
            'weekday' => 2,
            'shift_id' => $shift->id,
            'level' => 'unavailable',
        ]);

        $shift->delete();

        $this->assertSame(0, RecurringAvailability::count());
    }

    public function test_an_unknown_level_is_rejected(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $shift = $this->shift();

        $this->actingAs($user)
            ->put("/employees/{$employee->id}/availability/3/{$shift->id}", ['level' => 'maybe'])
            ->assertSessionHasErrors('level');

        $this->assertSame(0, RecurringAvailability::count());
    }

    public function test_an_out_of_range_weekday_or_unknown_shift_is_not_found(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $shift = $this->shift();

        $this->actingAs($user)->put("/employees/{$employee->id}/availability/0/{$shift->id}", ['level' => 'unavailable'])->assertNotFound();
        $this->actingAs($user)->put("/employees/{$employee->id}/availability/8/{$shift->id}", ['level' => 'unavailable'])->assertNotFound();
        $this->actingAs($user)->put("/employees/{$employee->id}/availability/3/999", ['level' => 'unavailable'])->assertNotFound();
        $this->actingAs($user)->put("/employees/{$employee->id}/availability/3/morning", ['level' => 'unavailable'])->assertNotFound();

        $this->assertSame(0, RecurringAvailability::count());
    }

    public function test_edit_payload_lists_shifts_and_availability(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $early = $this->shift('Early');
        $late = Shift::factory()->create(['name' => 'Late', 'start_time' => '14:00', 'end_time' => '22:00']);
        $employee->recurringAvailabilities()->create(['weekday' => 2, 'shift_id' => $early->id, 'level' => 'not_preferred']);

        $this->actingAs($user)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Form')
                ->has('shifts', 2)
                ->where('shifts.0.name', 'Early')
                ->where('shifts.1.name', 'Late')
                ->has('availability', 1)
                ->where('availability.0.weekday', 2)
                ->where('availability.0.shift_id', $early->id)
                ->where('availability.0.level', 'not_preferred')
            );
    }

    // ── Personal route ────────────────────────────────────────────────────

    public function test_employee_sets_a_cell_by_token(): void
    {
        $employee = Employee::factory()->create();
        $token = $this->token($employee);
        $shift = $this->shift();

        $this->put("/personal/{$token}/availability/4/{$shift->id}", ['level' => 'unavailable'])
            ->assertRedirect("/personal/{$token}");

        $this->assertDatabaseHas('recurring_availabilities', [
            'employee_id' => $employee->id,
            'weekday' => 4,
            'shift_id' => $shift->id,
            'level' => 'unavailable',
        ]);
    }

    public function test_a_bad_token_is_404(): void
    {
        $shift = $this->shift();

        $this->put("/personal/not-a-token/availability/4/{$shift->id}", ['level' => 'unavailable'])
            ->assertNotFound();

        $this->assertSame(0, RecurringAvailability::count());
    }

    public function test_show_payload_includes_shifts_and_availability(): void
    {
        $employee = Employee::factory()->create();
        $token = $this->token($employee);
        $shift = $this->shift();
        $employee->recurringAvailabilities()->create(['weekday' => 1, 'shift_id' => $shift->id, 'level' => 'not_preferred']);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personal/Show')
                ->has('shifts', 1)
                ->where('shifts.0.name', 'Early')
                ->has('availability', 1)
                ->where('availability.0.weekday', 1)
                ->where('availability.0.shift_id', $shift->id)
                ->where('availability.0.level', 'not_preferred')
            );
    }
}
