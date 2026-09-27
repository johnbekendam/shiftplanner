<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityStartDateTest extends TestCase
{
    use RefreshDatabase;

    private function linkedEmployee(array $attributes = []): array
    {
        $employee = Employee::factory()->create($attributes);
        $link = $employee->personalLink()->create(['token' => 'tok-'.$employee->id]);

        return [$employee, $link->token];
    }

    public function test_the_employee_sets_and_clears_their_start_date(): void
    {
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20]);

        $this->put("/personal/{$token}", ['weekly_hours' => 20, 'available_from' => '2026-11-02'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-11-02', $employee->fresh()->available_from->toDateString());

        $this->put("/personal/{$token}", ['weekly_hours' => 20, 'available_from' => null])
            ->assertSessionHasNoErrors();
        $this->assertNull($employee->fresh()->available_from);
    }

    public function test_an_omitted_start_date_keeps_the_stored_one(): void
    {
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20, 'available_from' => '2026-11-02']);

        $this->put("/personal/{$token}", ['weekly_hours' => 24])->assertSessionHasNoErrors();

        $this->assertSame('2026-11-02', $employee->fresh()->available_from->toDateString());
    }

    public function test_the_personal_update_rejects_an_invalid_start_date(): void
    {
        [, $token] = $this->linkedEmployee(['weekly_hours' => 20]);

        $this->put("/personal/{$token}", ['weekly_hours' => 20, 'available_from' => '02-11-2026'])
            ->assertSessionHasErrors('available_from');
    }

    public function test_the_admin_sets_the_start_date(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs(User::factory()->create())->put("/employees/{$employee->id}", [
            'first_name' => 'A', 'last_name' => 'B', 'weekly_hours' => 24, 'available_from' => '2026-11-02',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-11-02', $employee->fresh()->available_from->toDateString());
    }

    public function test_the_admin_clears_the_start_date_with_an_empty_string(): void
    {
        $employee = Employee::factory()->create(['available_from' => '2026-11-02']);

        $this->actingAs(User::factory()->create())->put("/employees/{$employee->id}", [
            'first_name' => 'A', 'last_name' => 'B', 'weekly_hours' => 24, 'available_from' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull($employee->fresh()->available_from);
    }

    public function test_both_pages_include_the_start_date(): void
    {
        [$employee, $token] = $this->linkedEmployee(['available_from' => '2026-11-02']);

        $this->get("/personal/{$token}")->assertInertia(fn ($page) => $page
            ->where('employee.available_from', '2026-11-02'));

        $this->actingAs(User::factory()->create())->get("/employees/{$employee->id}/edit")
            ->assertInertia(fn ($page) => $page->where('employee.available_from', '2026-11-02'));
    }

    public function test_a_start_date_change_is_audited_as_a_plain_date(): void
    {
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20, 'available_from' => '2026-10-01']);

        $this->put("/personal/{$token}", ['weekly_hours' => 20, 'available_from' => '2026-11-02']);
        $this->actingAs(User::factory()->create())->put("/employees/{$employee->id}", [
            'first_name' => $employee->first_name, 'last_name' => $employee->last_name,
            'email' => $employee->email, 'weekly_hours' => 20, 'available_from' => null,
        ]);

        $events = EmployeeAuditEvent::query()->orderBy('id')->get();
        $this->assertSame(['available_from' => '2026-10-01'], $events[0]->old_values);
        $this->assertSame(['available_from' => '2026-11-02'], $events[0]->new_values);
        $this->assertSame(['available_from' => '2026-11-02'], $events[1]->old_values);
        $this->assertSame(['available_from' => null], $events[1]->new_values);
    }
}
