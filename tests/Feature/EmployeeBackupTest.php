<?php

namespace Tests\Feature;

use App\Models\AvailabilityQuestion;
use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeBackupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function archive(array $employees): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('employees.json', json_encode([
            'version' => 1,
            'employees' => $employees,
        ], JSON_THROW_ON_ERROR));
    }

    public function test_guests_cannot_export_or_import_an_employee_archive(): void
    {
        $this->get('/employee-backup/export')->assertRedirect('/login');
        $this->post('/employee-backup/import')->assertRedirect('/login');
    }

    public function test_only_an_admin_can_open_the_backup_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MANAGER]))
            ->get('/employee-backup')
            ->assertForbidden();
    }

    public function test_an_admin_can_export_all_employee_configuration(): void
    {
        $businessLine = BusinessLine::factory()->create(['abbreviation' => 'OPS', 'description' => 'Operations']);
        $competence = Competence::factory()->create(['name' => 'Forklift']);
        $shift = Shift::factory()->create(['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00']);
        $question = AvailabilityQuestion::factory()->create(['text' => 'Can work weekends?']);
        $employee = Employee::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'weekly_hours' => 32,
            'weekly_hours_minimum' => 24,
            'business_line_id' => $businessLine->id,
            'confirmed' => true,
        ]);
        $employee->competences()->attach($competence);
        $employee->availabilityQuestions()->attach($question);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'weekday' => 1,
            'level' => 'unavailable',
        ]);
        EmployeeHoliday::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-12-24',
            'end_date' => '2026-12-31',
            'note' => 'Winter leave',
        ]);

        $response = $this->actingAs($this->admin())->get('/employee-backup/export');

        $response->assertOk()->assertDownload('employees-backup.json');
        $response->assertJsonPath('version', 1);
        $response->assertJsonPath('employees.0.email', 'jane@example.com');
        $response->assertJsonPath('employees.0.business_line.abbreviation', 'OPS');
        $response->assertJsonPath('employees.0.competences.0.name', 'Forklift');
        $response->assertJsonPath('employees.0.recurring_availability.0.shift.name', 'Early');
        $response->assertJsonPath('employees.0.holidays.0.note', 'Winter leave');
        $response->assertJsonPath('employees.0.availability_questions.0.text', 'Can work weekends?');
    }

    public function test_an_exported_archive_can_be_imported_without_validation_errors(): void
    {
        $businessLine = BusinessLine::factory()->create();
        $competence = Competence::factory()->create();
        $shift = Shift::factory()->create();
        $question = AvailabilityQuestion::factory()->create();
        $employee = Employee::factory()->create([
            'email' => 'jane@example.com',
            'weekly_hours' => 35,
            'weekly_hours_minimum' => null,
            'confirmed' => false,
            'business_line_id' => $businessLine->id,
        ]);
        $employee->competences()->attach($competence);
        $employee->availabilityQuestions()->attach($question);
        RecurringAvailability::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'weekday' => 1,
            'level' => 'available',
        ]);
        EmployeeHoliday::factory()->create(['employee_id' => $employee->id]);

        $export = $this->actingAs($this->admin())->get('/employee-backup/export');

        $response = $this->actingAs($this->admin())->post('/employee-backup/import', [
            'file' => UploadedFile::fake()->createWithContent('employees-backup.json', $export->getContent()),
        ]);

        $response->assertOk()->assertJson(['created' => 0, 'updated' => 1]);
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'email' => 'jane@example.com']);
    }

    public function test_import_uses_existing_references_for_employee_configuration(): void
    {
        $businessLine = BusinessLine::factory()->create(['abbreviation' => 'OPS', 'description' => 'Operations']);
        $competence = Competence::factory()->create(['name' => 'Forklift']);
        $shift = Shift::factory()->create(['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00']);
        AvailabilityQuestion::factory()->create(['text' => 'Can work weekends?']);

        $response = $this->actingAs($this->admin())->post('/employee-backup/import', [
            'file' => $this->archive([[
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'weekly_hours' => 32,
                'weekly_hours_minimum' => 24,
                'confirmed' => true,
                'business_line' => ['abbreviation' => $businessLine->abbreviation, 'description' => $businessLine->description],
                'competences' => [['name' => 'Forklift', 'read_only' => false]],
                'recurring_availability' => [[
                    'weekday' => 1,
                    'level' => 'unavailable',
                    'shift' => ['name' => $shift->name, 'start_time' => $shift->start_time, 'end_time' => $shift->end_time],
                ]],
                'holidays' => [['start_date' => '2026-12-24', 'end_date' => '2026-12-31', 'note' => 'Winter leave']],
                'availability_questions' => [['text' => 'Can work weekends?']],
            ]]),
        ]);

        $response->assertOk()->assertJson(['created' => 1, 'updated' => 0]);
        $employee = Employee::firstWhere('email', 'jane@example.com');
        $this->assertDatabaseCount('business_lines', 1);
        $this->assertDatabaseCount('competences', 1);
        $this->assertDatabaseHas('recurring_availabilities', ['employee_id' => $employee->id, 'shift_id' => $shift->id, 'weekday' => 1, 'level' => 'unavailable']);
        $this->assertDatabaseHas('employee_holidays', ['employee_id' => $employee->id, 'note' => 'Winter leave']);
        $this->assertSame(['Forklift'], $employee->competences()->pluck('name')->all());
        $this->assertSame(['Can work weekends?'], $employee->availabilityQuestions()->pluck('text')->all());
    }

    public function test_import_lists_each_missing_reference_without_creating_it(): void
    {
        $response = $this->actingAs($this->admin())->post('/employee-backup/import', [
            'file' => $this->archive([[
                'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
                'weekly_hours' => 32, 'weekly_hours_minimum' => null, 'confirmed' => true,
                'business_line' => ['abbreviation' => 'OPS', 'description' => 'Operations'],
                'competences' => [['name' => 'Forklift', 'read_only' => false]],
                'recurring_availability' => [[
                    'weekday' => 1, 'level' => 'available',
                    'shift' => ['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00'],
                ]],
                'holidays' => [],
                'availability_questions' => [['text' => 'Can work weekends?']],
            ]]),
        ]);

        $response->assertStatus(422)->assertJsonPath('errors', [
            'Record 1: business line "OPS" does not exist.',
            'Record 1: competence "Forklift" does not exist.',
            'Record 1: shift "Early (06:00-14:00)" does not exist.',
            'Record 1: availability question "Can work weekends?" does not exist.',
        ]);
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('business_lines', 0);
        $this->assertDatabaseCount('competences', 0);
        $this->assertDatabaseCount('shifts', 0);
        $this->assertDatabaseCount('availability_questions', 0);
    }

    public function test_import_replaces_existing_employee_configuration_by_email(): void
    {
        $oldCompetence = Competence::factory()->create(['name' => 'Old skill']);
        $newCompetence = Competence::factory()->create(['name' => 'New skill']);
        $employee = Employee::factory()->create(['email' => 'jane@example.com', 'weekly_hours' => 20]);
        $employee->competences()->attach($oldCompetence);

        $this->actingAs($this->admin())->post('/employee-backup/import', [
            'file' => $this->archive([[
                'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
                'weekly_hours' => 32, 'weekly_hours_minimum' => null, 'confirmed' => true,
                'business_line' => null, 'competences' => [['name' => 'New skill', 'read_only' => false]],
                'recurring_availability' => [], 'holidays' => [], 'availability_questions' => [],
            ]]),
        ])->assertOk()->assertJson(['created' => 0, 'updated' => 1]);

        $employee->refresh();
        $this->assertSame(32, $employee->weekly_hours);
        $this->assertSame(['New skill'], $employee->competences()->pluck('name')->all());
    }

    public function test_an_invalid_archive_makes_no_changes(): void
    {
        $response = $this->actingAs($this->admin())->post('/employee-backup/import', [
            'file' => $this->archive([[
                'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
                'weekly_hours' => 32, 'weekly_hours_minimum' => null, 'confirmed' => true,
                'business_line' => null, 'competences' => [],
                'recurring_availability' => [[
                    'weekday' => 1, 'level' => 'unavailable',
                    'shift' => ['name' => 'Missing', 'start_time' => '06:00', 'end_time' => '14:00'],
                ]],
                'holidays' => [], 'availability_questions' => [],
            ]]),
        ]);

        $response->assertStatus(422)->assertJsonStructure(['errors']);
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('business_lines', 0);
    }
}
