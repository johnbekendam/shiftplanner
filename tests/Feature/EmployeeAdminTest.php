<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Models\AvailabilityQuestion;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\Message;
use App\Models\RecurringAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/employees')->assertRedirect('/login');
    }

    public function test_index_lists_employees_with_weekly_hours(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Able', 'weekly_hours' => 32]);
        Employee::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Zeal']);

        $this->actingAs($user)->get('/employees')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Index')
                ->has('employees.data', 2)
                ->where('employees.data.0.name', 'Aaron Able')
                ->where('employees.data.0.weekly_hours', 32)
                ->missing('employees.data.0.department')
                ->missing('employees.data.0.shift_preference')
            );
    }

    public function test_index_search_filters_by_first_name_last_name_or_email(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['first_name' => 'Findme', 'last_name' => 'Jansen', 'email' => 'a@example.com']);
        Employee::factory()->create(['first_name' => 'Other', 'last_name' => 'Findme', 'email' => 'b@example.com']);
        Employee::factory()->create(['first_name' => 'Nomatch', 'last_name' => 'Person', 'email' => 'c@example.com']);

        $this->actingAs($user)->get('/employees?search=findme')->assertOk()
            ->assertInertia(fn ($page) => $page->has('employees.data', 2));

        $this->actingAs($user)->get('/employees?search=jansen')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('employees.data', 1)
                ->where('employees.data.0.name', 'Findme Jansen')
            );
    }

    public function test_index_handles_an_empty_or_blank_search(): void
    {
        $user = User::factory()->create();
        Employee::factory()->count(2)->create();

        foreach (['/employees', '/employees?search=', '/employees?search='.urlencode('   ')] as $url) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertInertia(fn ($page) => $page->has('employees.data', 2));
        }
    }

    public function test_create_employee_with_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/employees', [
            'first_name' => 'New',
            'last_name' => 'Hire',
            'email' => 'new.hire@example.com',
            'weekly_hours' => 32,
        ]);

        $new = Employee::firstWhere('email', 'new.hire@example.com');
        $response->assertRedirect("/employees/{$new->id}/edit");
        $this->assertDatabaseHas('employees', [
            'first_name' => 'New',
            'last_name' => 'Hire',
            'email' => 'new.hire@example.com',
            'weekly_hours' => 32,
        ]);
    }

    public function test_create_employee_validation_errors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/employees', [
            'first_name' => '',
            'last_name' => '',
            'email' => 'not-an-email',
            'weekly_hours' => null,
        ]);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'email', 'weekly_hours']);
        $this->assertSame(0, Employee::count());
    }

    public function test_create_rejects_weekly_hours_outside_the_allowed_set(): void
    {
        $user = User::factory()->create();

        foreach ([18, 22, 52, 40.5, 'many'] as $bad) {
            $this->actingAs($user)->post('/employees', [
                'first_name' => 'Bad',
                'last_name' => 'Hours',
                'email' => 'bad.hours@example.com',
                'weekly_hours' => $bad,
            ])->assertSessionHasErrors('weekly_hours');
        }

        $this->assertSame(0, Employee::count());
    }

    public function test_create_accepts_zero_weekly_hours_for_an_employee_below_the_minimum(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/employees', [
            'first_name' => 'Below',
            'last_name' => 'Minimum',
            'email' => 'below.minimum@example.com',
            'weekly_hours' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Employee::firstWhere('email', 'below.minimum@example.com')->weekly_hours);
    }

    public function test_email_must_be_unique_across_employees(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)->post('/employees', [
            'first_name' => 'Dup',
            'last_name' => 'Licate',
            'email' => 'taken@example.com',
            'weekly_hours' => 20,
        ])->assertSessionHasErrors('email');
    }

    public function test_edit_and_update_employee(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['first_name' => 'Old', 'last_name' => 'Name', 'weekly_hours' => 20]);

        $this->actingAs($user)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Form')
                ->where('employee.id', $employee->id)
                ->where('employee.weekly_hours', 20)
            );

        $response = $this->actingAs($user)->put("/employees/{$employee->id}", [
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => $employee->email,
            'weekly_hours' => 40,
        ]);

        $response->assertRedirect("/employees/{$employee->id}/edit");
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'New',
            'last_name' => 'Name',
            'weekly_hours' => 40,
        ]);
    }

    public function test_update_keeps_own_email_without_unique_conflict(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['email' => 'mine@example.com']);

        $this->actingAs($user)->put("/employees/{$employee->id}", [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => 'mine@example.com',
            'weekly_hours' => 24,
        ])->assertRedirect("/employees/{$employee->id}/edit");
    }

    public function test_personal_page_action_returns_a_link_url(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->getJson("/employees/{$employee->id}/personal-page");

        $response->assertOk();
        $url = $response->json('url');
        $this->assertStringContainsString('/personal/', $url);
        $this->assertDatabaseHas('employee_personal_links', ['employee_id' => $employee->id]);
    }

    public function test_personal_page_action_is_idempotent(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $first = $this->actingAs($user)->getJson("/employees/{$employee->id}/personal-page")->json('url');
        $second = $this->actingAs($user)->getJson("/employees/{$employee->id}/personal-page")->json('url');

        $this->assertSame($first, $second);
        $this->assertSame(1, $employee->personalLink()->count());
    }

    public function test_index_marks_whether_a_personal_link_was_sent(): void
    {
        $user = User::factory()->create();
        $sent = Employee::factory()->create(['first_name' => 'Aa', 'last_name' => 'Sent', 'email' => 'sent@example.com']);
        Employee::factory()->create(['first_name' => 'Bb', 'last_name' => 'Fresh', 'email' => 'fresh@example.com']);

        Message::factory()->sent()->create([
            'type' => MessageType::PersonalPageLink,
            'recipient_email' => 'sent@example.com',
        ]);
        // A draft to the same address does not count.
        Message::factory()->create([
            'type' => MessageType::PersonalPageLink,
            'recipient_email' => 'fresh@example.com',
            'status' => 'draft',
        ]);

        $this->actingAs($user)->get('/employees')->assertInertia(fn ($page) => $page
            ->where('employees.data.0.name', 'Aa Sent')
            ->where('employees.data.0.link_sent', true)
            ->where('employees.data.1.link_sent', false)
        );
    }

    public function test_edit_payload_carries_link_sent(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['email' => 'e@example.com']);
        Message::factory()->sent()->create([
            'type' => MessageType::PersonalPageLink,
            'recipient_email' => 'e@example.com',
        ]);

        $this->actingAs($user)->get("/employees/{$employee->id}/edit")->assertInertia(fn ($page) => $page
            ->where('employee.link_sent', true)
        );
    }

    public function test_guest_cannot_bulk_delete_employees(): void
    {
        $employee = Employee::factory()->create();

        $this->post('/employees/bulk-delete', ['ids' => [$employee->id]])
            ->assertRedirect('/login');

        $this->assertModelExists($employee);
    }

    public function test_manager_can_bulk_delete_only_selected_employees_and_dependent_data(): void
    {
        $manager = User::factory()->create();
        $selected = Employee::factory()->create();
        $untouched = Employee::factory()->create();
        $holiday = EmployeeHoliday::factory()->create(['employee_id' => $selected->id]);
        $availability = RecurringAvailability::factory()->create(['employee_id' => $selected->id]);
        $competence = Competence::factory()->create();
        $question = AvailabilityQuestion::factory()->create();
        $selected->competences()->attach($competence);
        $selected->availabilityQuestions()->attach($question);
        $selected->personalLink()->create(['token' => 'delete-me']);

        $response = $this->actingAs($manager)->post('/employees/bulk-delete', [
            'ids' => [$selected->id],
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertModelMissing($selected);
        $this->assertModelExists($untouched);
        $this->assertModelMissing($holiday);
        $this->assertModelMissing($availability);
        $this->assertDatabaseMissing('competence_employee', ['employee_id' => $selected->id]);
        $this->assertDatabaseMissing('availability_question_employee', ['employee_id' => $selected->id]);
        $this->assertDatabaseMissing('employee_personal_links', ['employee_id' => $selected->id]);
    }

    public function test_admin_can_bulk_delete_employees(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($admin)->post('/employees/bulk-delete', ['ids' => [$employee->id]])
            ->assertRedirect();

        $this->assertModelMissing($employee);
    }

    public function test_bulk_delete_keeps_and_unlinks_a_linked_user_account(): void
    {
        $manager = User::factory()->create();
        $employee = Employee::factory()->create();
        $linkedUser = User::factory()->create(['employee_id' => $employee->id]);

        $this->actingAs($manager)->post('/employees/bulk-delete', ['ids' => [$employee->id]])
            ->assertRedirect();

        $this->assertModelExists($linkedUser);
        $this->assertNull($linkedUser->fresh()->employee_id);
    }

    public function test_bulk_delete_requires_existing_distinct_employee_ids(): void
    {
        $manager = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($manager)->post('/employees/bulk-delete', ['ids' => []])
            ->assertSessionHasErrors('ids');

        $this->actingAs($manager)->post('/employees/bulk-delete', [
            'ids' => [$employee->id, $employee->id, 999999],
        ])->assertSessionHasErrors(['ids.1', 'ids.2']);

        $this->assertModelExists($employee);
    }
}
