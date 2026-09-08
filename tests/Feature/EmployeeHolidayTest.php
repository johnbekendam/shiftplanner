<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHolidayTest extends TestCase
{
    use RefreshDatabase;

    private function linkedToken(Employee $employee): string
    {
        return $employee->personalLink()->create(['token' => 'tok-'.$employee->id])->token;
    }

    // ── Manager routes ──────────────────────────────────────────────────────

    public function test_guest_cannot_add_a_holiday(): void
    {
        $employee = Employee::factory()->create();

        $this->post("/employees/{$employee->id}/holidays", [
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-10',
        ])->assertRedirect('/login');

        $this->assertSame(0, EmployeeHoliday::count());
    }

    public function test_manager_adds_a_holiday(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user)->post("/employees/{$employee->id}/holidays", [
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-14',
            'note' => 'Family trip',
        ])->assertRedirect();

        $this->assertDatabaseHas('employee_holidays', [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-14',
            'note' => 'Family trip',
        ]);
    }

    public function test_add_holiday_rejects_end_before_start(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user)->post("/employees/{$employee->id}/holidays", [
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-01',
        ])->assertSessionHasErrors('end_date');

        $this->assertSame(0, EmployeeHoliday::count());
    }

    public function test_add_holiday_requires_both_dates(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user)->post("/employees/{$employee->id}/holidays", [
            'note' => 'no dates',
        ])->assertSessionHasErrors(['start_date', 'end_date']);
    }

    public function test_manager_deletes_a_holiday(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $holiday = $employee->holidays()->create([
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
        ]);

        $this->actingAs($user)
            ->delete("/employees/{$employee->id}/holidays/{$holiday->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('employee_holidays', ['id' => $holiday->id]);
    }

    public function test_manager_cannot_delete_a_holiday_through_the_wrong_employee(): void
    {
        $user = User::factory()->create();
        $owner = Employee::factory()->create();
        $other = Employee::factory()->create();
        $holiday = $owner->holidays()->create([
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
        ]);

        $this->actingAs($user)
            ->delete("/employees/{$other->id}/holidays/{$holiday->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('employee_holidays', ['id' => $holiday->id]);
    }

    public function test_edit_payload_lists_holidays_in_date_order(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $employee->holidays()->create(['start_date' => '2026-09-01', 'end_date' => '2026-09-03']);
        $employee->holidays()->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-05', 'note' => 'Early']);

        $this->actingAs($user)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Form')
                ->has('holidays', 2)
                ->where('holidays.0.start_date', '2026-06-01')
                ->where('holidays.0.note', 'Early')
                ->where('holidays.1.start_date', '2026-09-01')
            );
    }

    // ── Personal routes ────────────────────────────────────────────────────

    public function test_employee_adds_a_holiday_by_token(): void
    {
        $employee = Employee::factory()->create();
        $token = $this->linkedToken($employee);

        $this->post("/personal/{$token}/holidays", [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-07',
        ])->assertRedirect("/personal/{$token}");

        $this->assertDatabaseHas('employee_holidays', [
            'employee_id' => $employee->id,
            'start_date' => '2026-08-01',
        ]);
    }

    public function test_add_holiday_with_a_bad_token_is_404(): void
    {
        $this->post('/personal/not-a-real-token/holidays', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-07',
        ])->assertNotFound();

        $this->assertSame(0, EmployeeHoliday::count());
    }

    public function test_employee_deletes_their_own_holiday_by_token(): void
    {
        $employee = Employee::factory()->create();
        $token = $this->linkedToken($employee);
        $holiday = $employee->holidays()->create([
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-07',
        ]);

        $this->delete("/personal/{$token}/holidays/{$holiday->id}")
            ->assertRedirect("/personal/{$token}");

        $this->assertDatabaseMissing('employee_holidays', ['id' => $holiday->id]);
    }

    public function test_employee_cannot_delete_another_employees_holiday(): void
    {
        $me = Employee::factory()->create();
        $token = $this->linkedToken($me);
        $other = Employee::factory()->create();
        $holiday = $other->holidays()->create([
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-07',
        ]);

        $this->delete("/personal/{$token}/holidays/{$holiday->id}")->assertNotFound();

        $this->assertDatabaseHas('employee_holidays', ['id' => $holiday->id]);
    }

    public function test_show_payload_includes_holidays(): void
    {
        $employee = Employee::factory()->create();
        $token = $this->linkedToken($employee);
        $employee->holidays()->create(['start_date' => '2026-08-01', 'end_date' => '2026-08-07', 'note' => 'Trip']);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personal/Show')
                ->has('holidays', 1)
                ->where('holidays.0.note', 'Trip')
                ->where('holidays.0.start_date', '2026-08-01')
            );
    }
}
