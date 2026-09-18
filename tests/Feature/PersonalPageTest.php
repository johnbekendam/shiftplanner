<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\PlanningSettings;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
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
        $line = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        [$employee, $token] = $this->linkedEmployee([
            'first_name' => 'Pat', 'last_name' => 'Person',
            'email' => 'pat@example.com',
            'weekly_hours' => 28,
            'business_line_id' => $line->id,
        ]);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personal/Show')
                ->where('employee.first_name', 'Pat')
                ->where('employee.last_name', 'Person')
                ->where('employee.email', 'pat@example.com')
                ->where('employee.weekly_hours', 28)
                ->where('weeklyHoursMinimum', 20)
                ->where('employee.business_line_id', $line->id)
                ->has('businessLines', 1)
                ->where('businessLines.0.abbreviation', 'PMP')
                ->missing('employee.shift_preference')
            );
    }

    public function test_employee_can_set_their_business_line(): void
    {
        $line = BusinessLine::factory()->create();
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20]);

        $this->put("/personal/{$token}", ['weekly_hours' => 20, 'business_line_id' => $line->id])
            ->assertRedirect("/personal/{$token}")
            ->assertSessionHasNoErrors();

        $this->assertSame($line->id, $employee->fresh()->business_line_id);
    }

    public function test_employee_can_clear_their_business_line(): void
    {
        $line = BusinessLine::factory()->create();
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20, 'business_line_id' => $line->id]);

        $this->put("/personal/{$token}", ['weekly_hours' => 20, 'business_line_id' => null])
            ->assertRedirect("/personal/{$token}")
            ->assertSessionHasNoErrors();

        $this->assertNull($employee->fresh()->business_line_id);
    }

    public function test_an_unknown_business_line_is_rejected_on_the_personal_page(): void
    {
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20]);

        $this->put("/personal/{$token}", ['weekly_hours' => 20, 'business_line_id' => 999])
            ->assertSessionHasErrors('business_line_id');
        $this->assertNull($employee->fresh()->business_line_id);
    }

    public function test_weekly_hours_only_update_still_works(): void
    {
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20]);

        $this->put("/personal/{$token}", ['weekly_hours' => 40])
            ->assertRedirect("/personal/{$token}")
            ->assertSessionHasNoErrors();

        $this->assertSame(40, $employee->fresh()->weekly_hours);
    }

    public function test_a_write_route_with_a_malformed_token_does_not_resolve(): void
    {
        $this->put('/personal/definitely-not-a-real-token', [
            'weekly_hours' => 40,
        ])->assertNotFound();
    }

    public function test_visiting_an_invalid_token_redirects_to_signup_with_an_error(): void
    {
        $this->get('/personal/definitely-not-a-real-token')
            ->assertRedirect('/signup')
            ->assertSessionHas('error');
    }

    public function test_visiting_a_withdrawn_employees_old_link_redirects_to_signup(): void
    {
        [, $token] = $this->linkedEmployee();
        $this->delete("/personal/{$token}");

        $this->get("/personal/{$token}")
            ->assertRedirect('/signup')
            ->assertSessionHas('error');
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

    public function test_weekly_hours_update_accepts_any_whole_value_and_preserves_below_minimum(): void
    {
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20]);

        $this->put("/personal/{$token}", ['weekly_hours' => 37])
            ->assertSessionHasNoErrors();
        $this->assertSame(37, $employee->fresh()->weekly_hours);

        $employee->update(['weekly_hours_minimum' => 30]);
        $this->put("/personal/{$token}", ['weekly_hours' => 29])
            ->assertSessionHasNoErrors();
        $this->assertSame(29, $employee->fresh()->weekly_hours);
    }

    public function test_weekly_hours_update_rejects_a_value_outside_the_allowed_range(): void
    {
        [$employee, $token] = $this->linkedEmployee(['weekly_hours' => 20]);

        foreach ([-1, 49, 20.5, 'many'] as $value) {
            $this->put("/personal/{$token}", ['weekly_hours' => $value])
                ->assertSessionHasErrors('weekly_hours');
        }

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

    // ── Withdraw: self-service permanent deletion ─────────────────────────

    public function test_employee_can_withdraw_and_is_redirected_to_signup(): void
    {
        [$employee, $token] = $this->linkedEmployee();

        $this->delete("/personal/{$token}")
            ->assertRedirect('/signup')
            ->assertSessionHas('warning');

        $this->assertModelMissing($employee);
    }

    public function test_withdrawing_removes_dependent_records(): void
    {
        [$employee, $token] = $this->linkedEmployee();
        $employee->holidays()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-02']);

        $this->delete("/personal/{$token}");

        $this->assertSame(0, $employee->holidays()->count());
        $this->assertSame(0, $employee->personalLink()->count());
    }

    public function test_withdrawing_is_blocked_when_a_manager_has_closed_employee_changes(): void
    {
        PlanningSettings::current()->update(['allow_employee_changes' => false]);
        [$employee, $token] = $this->linkedEmployee();

        $this->delete("/personal/{$token}")->assertForbidden();

        $this->assertModelExists($employee);
    }

    public function test_withdrawing_with_a_malformed_token_is_not_found(): void
    {
        $this->delete('/personal/definitely-not-a-real-token')->assertNotFound();
    }

    public function test_planned_shifts_only_includes_published_weeks(): void
    {
        [$employee, $token] = $this->linkedEmployee();
        $workcenter = Workcenter::factory()->create(['name' => 'Line 1']);
        $shift = Shift::factory()->create(['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00']);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
            'date' => '2026-09-08', // Tuesday, week starting 2026-09-07
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
            'date' => '2026-09-15', // Tuesday, week starting 2026-09-14, not published
        ]);
        PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $workcenter->id]);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('plannedShifts', 1)
                ->where('plannedShifts.0.weekStart', '2026-09-07')
                ->where('plannedShifts.0.weekEnd', '2026-09-13')
                ->has('plannedShifts.0.assignments', 1)
                ->where('plannedShifts.0.assignments.0.date', '2026-09-08')
                ->where('plannedShifts.0.assignments.0.workcenter_name', 'Line 1')
                ->where('plannedShifts.0.assignments.0.shift_name', 'Early')
                ->where('plannedShifts.0.assignments.0.published', true)
            );
    }

    public function test_planned_shifts_only_includes_assignments_from_a_published_workcenter(): void
    {
        [$employee, $token] = $this->linkedEmployee();
        $published = Workcenter::factory()->create(['name' => 'Line 1']);
        $draft = Workcenter::factory()->create(['name' => 'Line 2']);
        $shift = Shift::factory()->create();
        // Both assignments fall in the same week; only Line 1 is published for it.
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $published->id, 'shift_id' => $shift->id,
            'date' => '2026-09-08',
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $draft->id, 'shift_id' => $shift->id,
            'date' => '2026-09-09',
        ]);
        PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $published->id]);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('plannedShifts', 1)
                ->has('plannedShifts.0.assignments', 1)
                ->where('plannedShifts.0.assignments.0.workcenter_name', 'Line 1')
            );
    }

    public function test_planned_shifts_is_empty_when_nothing_is_published(): void
    {
        [$employee, $token] = $this->linkedEmployee();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08',
        ]);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('plannedShifts', []));
    }
}
