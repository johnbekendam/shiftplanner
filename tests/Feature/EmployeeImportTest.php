<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('employees.csv', $body);
    }

    private function import(string $body): TestResponse
    {
        return $this->actingAs($this->admin())->post('/import', ['file' => $this->csv($body)]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/import')->assertRedirect('/login');
    }

    public function test_a_manager_may_not_open_the_import_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MANAGER]))
            ->get('/import')
            ->assertForbidden();
    }

    public function test_an_admin_sees_the_import_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/import')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('EmployeeImport'));
    }

    public function test_it_creates_new_employees_from_the_csv(): void
    {
        $response = $this->import(
            "name,email\n".
            "Jane Doe,jane@example.com\n".
            "John Roe,john@example.com\n"
        );

        $response->assertOk()->assertJson(['created' => 2, 'updated' => 0]);
        $this->assertDatabaseHas('employees', ['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->assertDatabaseHas('employees', ['name' => 'John Roe', 'email' => 'john@example.com']);
    }

    public function test_new_employees_take_the_default_weekly_hours(): void
    {
        $this->import("name,email\nJane Doe,jane@example.com\n")->assertOk();

        $this->assertSame(20, Employee::firstWhere('email', 'jane@example.com')->weekly_hours);
    }

    public function test_it_updates_the_name_of_an_existing_employee_matched_by_email(): void
    {
        Employee::factory()->create(['name' => 'Old Name', 'email' => 'jane@example.com']);

        $response = $this->import("name,email\nJane Doe,jane@example.com\n");

        $response->assertOk()->assertJson(['created' => 0, 'updated' => 1]);
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseHas('employees', ['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    }

    public function test_re_running_an_unchanged_file_changes_nothing(): void
    {
        $body = "name,email\nJane Doe,jane@example.com\nJohn Roe,john@example.com\n";

        $this->import($body)->assertOk()->assertJson(['created' => 2, 'updated' => 0]);
        $this->import($body)->assertOk()->assertJson(['created' => 0, 'updated' => 0]);

        $this->assertDatabaseCount('employees', 2);
    }

    public function test_surrounding_whitespace_is_trimmed(): void
    {
        $this->import("name,email\n  Jane Doe  ,  jane@example.com  \n")->assertOk();

        $this->assertDatabaseHas('employees', ['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    }

    public function test_a_semicolon_delimited_file_is_accepted(): void
    {
        $response = $this->import(
            "name;email\n".
            "Jane Doe;jane@example.com\n".
            "John Roe;john@example.com\n"
        );

        $response->assertOk()->assertJson(['created' => 2]);
        $this->assertDatabaseHas('employees', ['email' => 'jane@example.com']);
    }

    public function test_an_invalid_email_rejects_the_whole_file(): void
    {
        $response = $this->import(
            "name,email\n".
            "Jane Doe,jane@example.com\n".
            "Bad Row,not-an-email\n"
        );

        $response->assertStatus(422)->assertJsonStructure(['errors']);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_a_row_without_exactly_two_columns_rejects_the_file(): void
    {
        $response = $this->import(
            "name,email\n".
            "Jane Doe,jane@example.com\n".
            "Missing Email\n"
        );

        $response->assertStatus(422)->assertJsonStructure(['errors']);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_a_row_with_three_columns_rejects_the_file(): void
    {
        $response = $this->import(
            "name,email\n".
            "Jane Doe,jane@example.com,extra\n"
        );

        $response->assertStatus(422);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_a_duplicate_email_within_the_file_rejects_it(): void
    {
        $response = $this->import(
            "name,email\n".
            "Jane Doe,jane@example.com\n".
            "Jane D,JANE@example.com\n"
        );

        $response->assertStatus(422)->assertJsonStructure(['errors']);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_a_header_only_file_is_rejected(): void
    {
        $this->import("name,email\n")->assertStatus(422);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_a_non_csv_upload_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post('/import', [
            'file' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ]);

        $response->assertStatus(422)->assertJsonStructure(['errors']);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_the_file_field_is_required(): void
    {
        $this->actingAs($this->admin())->post('/import', [])->assertStatus(422);
    }
}
