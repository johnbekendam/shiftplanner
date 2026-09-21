<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\PublishedWeek;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use Carbon\Carbon;
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

    public function test_missing_availability_rows_say_whether_the_employee_has_an_email(): void
    {
        $this->admin();
        $with = Employee::factory()->create(['first_name' => 'Aaron', 'weekly_hours' => 32, 'email' => 'a@example.com']);
        $without = Employee::factory()->create(['first_name' => 'Zoe', 'weekly_hours' => 32, 'email' => null]);

        $this->get('/reports')->assertInertia(fn ($page) => $page
            ->where('employees.0.id', $with->id)
            ->where('employees.0.has_email', true)
            ->where('employees.1.id', $without->id)
            ->where('employees.1.has_email', false)
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

    public function test_unconfirmed_employee_appears_by_default(): void
    {
        $this->admin();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['weekly_hours' => 32, 'confirmed' => false]);

        $this->get("/reports?shift={$shift->id}")->assertInertia(fn ($page) => $page
            ->where('employees.0.id', $employee->id)
            ->where('filters.unconfirmed', true)
        );
    }

    public function test_unconfirmed_employee_is_excluded_when_the_toggle_is_off(): void
    {
        $this->admin();
        $shift = Shift::factory()->create();
        Employee::factory()->create(['weekly_hours' => 32, 'confirmed' => false]);

        $this->get("/reports?shift={$shift->id}&unconfirmed=0")->assertInertia(fn ($page) => $page
            ->where('employees', [])
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

    // ── Competence report ──────────────────────────────────────────────

    public function test_competence_report_defaults_to_missing_mode_with_no_rows_without_selection(): void
    {
        $this->admin();
        $competence = Competence::factory()->create(['name' => 'Forklift', 'position' => 1]);
        Employee::factory()->create();

        $this->get('/reports')->assertInertia(fn ($page) => $page
            ->where('competences.0.id', $competence->id)
            ->where('competences.0.name', 'Forklift')
            ->where('filters.competence_mode', 'missing')
            ->where('filters.competence_id', null)
            ->where('competenceReport', [])
        );
    }

    public function test_missing_competence_report_lists_employees_missing_the_selected_competence(): void
    {
        $this->admin();
        $forklift = Competence::factory()->create(['name' => 'Forklift', 'position' => 1]);
        $firstAid = Competence::factory()->create(['name' => 'First aid', 'position' => 2]);
        $ann = Employee::factory()->create(['first_name' => 'Ann', 'last_name' => 'Ant']);
        $bo = Employee::factory()->create(['first_name' => 'Bo', 'last_name' => 'Bee']);
        $cy = Employee::factory()->create(['first_name' => 'Cy', 'last_name' => 'Cat']);
        $ann->competences()->attach($forklift);
        $bo->competences()->attach([$forklift->id, $firstAid->id]);

        $this->get("/reports?competence_mode=missing&competence={$firstAid->id}")
            ->assertInertia(fn ($page) => $page
                ->has('competenceReport', 2)
                ->where('competenceReport.0.id', $ann->id)
                ->where('competenceReport.0.name', 'Ann Ant')
                ->where('competenceReport.0.competence_names', ['First aid'])
                ->where('competenceReport.1.id', $cy->id)
                ->where('competenceReport.1.name', 'Cy Cat')
                ->where('competenceReport.1.competence_names', ['First aid'])
                ->where('filters.competence_id', $firstAid->id)
            );
    }

    public function test_has_competence_report_lists_only_employees_with_the_selected_competence(): void
    {
        $this->admin();
        $forklift = Competence::factory()->create(['name' => 'Forklift', 'position' => 1]);
        $firstAid = Competence::factory()->create(['name' => 'First aid', 'position' => 2]);
        $ann = Employee::factory()->create(['first_name' => 'Ann', 'last_name' => 'Ant']);
        $bo = Employee::factory()->create(['first_name' => 'Bo', 'last_name' => 'Bee']);
        $ann->competences()->attach($forklift);
        $bo->competences()->attach([$forklift->id, $firstAid->id]);

        $this->get("/reports?competence_mode=has&competence={$firstAid->id}")
            ->assertInertia(fn ($page) => $page
                ->has('competenceReport', 1)
                ->where('competenceReport.0.id', $bo->id)
                ->where('competenceReport.0.name', 'Bo Bee')
            ->where('competenceReport.0.competence_names', ['First aid'])
                ->where('filters.competence_mode', 'has')
            );
    }

    public function test_competence_report_accepts_nested_competence_query_values(): void
    {
        $this->admin();
        $forklift = Competence::factory()->create(['name' => 'Forklift', 'position' => 1]);
        $firstAid = Competence::factory()->create(['name' => 'First aid', 'position' => 2]);
        $ann = Employee::factory()->create(['first_name' => 'Ann', 'last_name' => 'Ant']);
        $bo = Employee::factory()->create(['first_name' => 'Bo', 'last_name' => 'Bee']);
        $ann->competences()->attach($forklift);
        $bo->competences()->attach([$forklift->id, $firstAid->id]);

        $this->get("/reports?competence_mode=has&competences[0][0]={$forklift->id}&competences[1][0]={$firstAid->id}")
            ->assertInertia(fn ($page) => $page
            ->has('competenceReport', 2)
            ->where('competenceReport.0.id', $ann->id)
            ->where('competenceReport.1.id', $bo->id)
            ->where('filters.competence_id', $forklift->id)
            );
    }

    // ── Uninformed planning ─────────────────────────────────────────────

    private function plan(Employee $employee, string $date, ?string $informedAt = null): void
    {
        $workcenter = Workcenter::factory()->create();
        PublishedWeek::query()->create([
            'week_start' => Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString(),
            'workcenter_id' => $workcenter->id,
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id,
            'date' => $date, 'informed_at' => $informedAt,
        ]);
    }

    public function test_the_uninformed_report_still_lists_employees_without_an_email(): void
    {
        $this->admin();
        $nomail = Employee::factory()->create(['email' => null]);
        $this->plan($nomail, '2026-09-22');

        $this->get('/reports')->assertInertia(fn ($page) => $page
            ->has('uninformedPlanning', 1)
            ->where('uninformedPlanning.0.id', $nomail->id)
            ->where('uninformedPlanning.0.has_email', false)
        );
    }

    public function test_the_report_lists_employees_with_uninformed_published_planning(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->admin();
        $line = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        $uninformed = Employee::factory()->create(['first_name' => 'Ann', 'last_name' => 'Ant', 'business_line_id' => $line->id]);
        $informed = Employee::factory()->create();
        $this->plan($uninformed, '2026-09-22');
        $this->plan($uninformed, '2026-09-23');
        $this->plan($informed, '2026-09-22', '2026-09-19 08:00:00');

        $this->get('/reports')->assertInertia(fn ($page) => $page
            ->has('uninformedPlanning', 1)
            ->where('uninformedPlanning.0.id', $uninformed->id)
            ->where('uninformedPlanning.0.name', 'Ann Ant')
            ->where('uninformedPlanning.0.business_line', 'PMP')
            ->where('uninformedPlanning.0.uninformed_count', 2)
            ->where('uninformedPlanning.0.first_date', '2026-09-22')
        );
        Carbon::setTestNow();
    }

    public function test_the_uninformed_planning_report_filters_by_business_line(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->admin();
        $line = BusinessLine::factory()->create();
        $inLine = Employee::factory()->create(['business_line_id' => $line->id]);
        $this->plan($inLine, '2026-09-22');
        $this->plan(Employee::factory()->create(), '2026-09-22');

        $this->get("/reports?planning_business_line={$line->id}")->assertInertia(fn ($page) => $page
            ->has('uninformedPlanning', 1)
            ->where('uninformedPlanning.0.id', $inLine->id)
            ->where('filters.planning_business_line', $line->id)
        );
        Carbon::setTestNow();
    }

    public function test_the_uninformed_planning_filter_is_null_by_default(): void
    {
        $this->admin();

        $this->get('/reports')->assertInertia(fn ($page) => $page
            ->where('filters.planning_business_line', null)
            ->where('uninformedPlanning', []));
    }
}
