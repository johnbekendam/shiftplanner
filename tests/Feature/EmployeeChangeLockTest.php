<?php

namespace Tests\Feature;

use App\Models\AvailabilityQuestion;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\PlanningSettings;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeChangeLockTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    private function lock(bool $allow): void
    {
        PlanningSettings::current()->update(['allow_employee_changes' => $allow]);
    }

    /** @return array{0: Employee, 1: string} */
    private function linkedEmployee(): array
    {
        $employee = Employee::factory()->create(['weekly_hours' => 20]);
        $token = $employee->personalLink()->create(['token' => 'tok-lock'])->token;

        return [$employee, $token];
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'fte_hours' => 40,
            'period_start' => null,
            'period_end' => null,
            'allow_employee_changes' => true,
        ], $overrides);
    }

    public function test_it_defaults_to_allowed(): void
    {
        $this->assertTrue(PlanningSettings::current()->allow_employee_changes);
    }

    public function test_guest_cannot_change_the_flag(): void
    {
        $this->put('/settings/period', $this->validPayload(['allow_employee_changes' => false]))
            ->assertRedirect('/login');
    }

    public function test_manager_cannot_change_the_flag(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put('/settings/period', $this->validPayload(['allow_employee_changes' => false]))
            ->assertForbidden();
        $this->assertTrue(PlanningSettings::current()->allow_employee_changes);
    }

    public function test_admin_turns_employee_changes_off_and_on(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/period', $this->validPayload(['allow_employee_changes' => false]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse(PlanningSettings::current()->fresh()->allow_employee_changes);

        $this->put('/settings/period', $this->validPayload(['allow_employee_changes' => true]))
            ->assertSessionHasNoErrors();
        $this->assertTrue(PlanningSettings::current()->fresh()->allow_employee_changes);
    }

    public function test_the_flag_must_be_a_boolean(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/period', $this->validPayload(['allow_employee_changes' => 'maybe']))
            ->assertSessionHasErrors('allow_employee_changes');
    }

    public function test_settings_index_carries_the_flag(): void
    {
        $this->actingAsAdmin();

        $this->get('/settings')->assertInertia(fn ($page) => $page
            ->where('period.allow_employee_changes', true)
        );
    }

    // ── Enforcement on the personal write routes ────────────────────────

    public function test_personal_writes_are_blocked_when_changes_are_off(): void
    {
        [$employee, $token] = $this->linkedEmployee();
        $shift = Shift::create(['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00']);
        $competence = Competence::create(['name' => 'Forklift', 'position' => 1]);
        $question = AvailabilityQuestion::create(['text' => 'Weekend?', 'position' => 1]);
        $holiday = $employee->holidays()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-02']);

        $this->lock(false);

        $this->put("/personal/{$token}", ['weekly_hours' => 40])->assertForbidden();
        $this->post("/personal/{$token}/holidays", ['start_date' => '2026-02-01', 'end_date' => '2026-02-02'])->assertForbidden();
        $this->delete("/personal/{$token}/holidays/{$holiday->id}")->assertForbidden();
        $this->put("/personal/{$token}/availability/1/{$shift->id}", ['level' => 'unavailable'])->assertForbidden();
        $this->put("/personal/{$token}/competences/{$competence->id}")->assertForbidden();
        $this->delete("/personal/{$token}/competences/{$competence->id}")->assertForbidden();
        $this->put("/personal/{$token}/questions/{$question->id}", ['answer' => true])->assertForbidden();

        $this->assertSame(20, $employee->fresh()->weekly_hours);
    }

    public function test_personal_writes_work_when_changes_are_on(): void
    {
        [$employee, $token] = $this->linkedEmployee();
        $competence = Competence::create(['name' => 'Forklift', 'position' => 1]);

        // Default is on.
        $this->put("/personal/{$token}", ['weekly_hours' => 40])->assertRedirect("/personal/{$token}");
        $this->put("/personal/{$token}/competences/{$competence->id}")->assertRedirect("/personal/{$token}");

        $this->assertSame(40, $employee->fresh()->weekly_hours);
        $this->assertTrue($employee->competences()->whereKey($competence->id)->exists());
    }

    public function test_the_personal_page_stays_viewable_when_changes_are_off(): void
    {
        [, $token] = $this->linkedEmployee();
        $this->lock(false);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personal/Show')
                ->where('editable', false)
            );
    }

    public function test_the_personal_page_reports_editable_when_changes_are_on(): void
    {
        [, $token] = $this->linkedEmployee();

        $this->get("/personal/{$token}")->assertInertia(fn ($page) => $page
            ->where('editable', true)
        );
    }

    public function test_manager_editing_is_unaffected_when_changes_are_off(): void
    {
        [$employee] = $this->linkedEmployee();
        $this->lock(false);
        $this->actingAsAdmin();

        $this->put("/employees/{$employee->id}", [
            'name' => 'Still Editable',
            'email' => $employee->email,
            'weekly_hours' => 40,
        ])->assertRedirect('/employees');

        $this->assertSame('Still Editable', $employee->fresh()->name);
    }
}
