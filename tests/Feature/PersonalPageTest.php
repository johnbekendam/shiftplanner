<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalPageTest extends TestCase
{
    use RefreshDatabase;

    private function linkedEmployee(array $attributes = []): array
    {
        $employee = Employee::factory()->create($attributes);
        $link = $employee->personalLink()->create(['token' => 'tok-'.$employee->id]);

        return [$employee, $link->token];
    }

    public function test_personal_page_opens_by_token_without_auth(): void
    {
        [$employee, $token] = $this->linkedEmployee([
            'first_name' => 'Pat', 'last_name' => 'Person',
            'email' => 'pat@example.com',
            'weekly_hours' => 28,
        ]);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personal/Show')
                ->where('employee.first_name', 'Pat')
                ->where('employee.last_name', 'Person')
                ->where('employee.email', 'pat@example.com')
                ->where('employee.weekly_hours', 28)
                ->missing('employee.shift_preference')
            );
    }

    public function test_malformed_token_does_not_resolve(): void
    {
        $this->get('/personal/definitely-not-a-real-token')->assertNotFound();
        $this->put('/personal/definitely-not-a-real-token', [
            'weekly_hours' => 40,
        ])->assertNotFound();
    }

    public function test_employee_can_save_new_weekly_hours(): void
    {
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20]);

        $response = $this->put("/personal/{$token}", [
            'weekly_hours' => 40,
        ]);

        $response->assertRedirect("/personal/{$token}");
        $this->assertSame(40, $employee->fresh()->weekly_hours);
    }

    public function test_employee_can_save_zero_weekly_hours_when_below_the_minimum(): void
    {
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20]);

        $this->put("/personal/{$token}", ['weekly_hours' => 0])
            ->assertRedirect("/personal/{$token}")
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $employee->fresh()->weekly_hours);
    }

    public function test_weekly_hours_update_rejects_a_value_outside_the_allowed_set(): void
    {
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20]);

        $this->put("/personal/{$token}", ['weekly_hours' => 22])
            ->assertSessionHasErrors('weekly_hours');
        $this->assertSame(20, $employee->fresh()->weekly_hours);
    }

    public function test_personal_page_exposes_no_other_employees(): void
    {
        [$employee, $token] = $this->linkedEmployee(['first_name' => 'Only', 'last_name' => 'Me']);
        Employee::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);

        $response = $this->get("/personal/{$token}");

        $response->assertOk();
        $response->assertDontSee('Someone Else');
    }
}
