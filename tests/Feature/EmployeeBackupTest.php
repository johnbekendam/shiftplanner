<?php

namespace Tests\Feature;

use App\Models\AvailabilityQuestion;
use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\EmployeeAuditEvent;
use App\Models\EmployeeHoliday;
use App\Models\PlanGenerationRun;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\User;
use App\Services\ApplicationBackup;
use App\Services\EmployeeAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
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

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/attachment; filename=\d{8}-\d{6}-shiftplanner-backup\.json/',
            $response->headers->get('Content-Disposition'),
        );
        $response->assertJsonPath('version', 2);
        $response->assertJsonPath('format', 'shiftplanner-business-archive');
        $response->assertJsonPath('data.employees.0.email', 'jane@example.com');
        $response->assertJsonPath('data.business_lines.0.abbreviation', 'OPS');
        $response->assertJsonPath('data.competences.0.name', 'Forklift');
        $response->assertJsonPath('data.recurring_availabilities.0.shift_id', $shift->id);
        $response->assertJsonPath('data.employee_holidays.0.note', 'Winter leave');
        $this->assertCount(1, $response->json('data.availability_question_employee'));
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

        $response->assertOk()->assertJsonPath('version', 2)->assertJsonPath('imported', 1);
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'email' => 'jane@example.com']);
    }

    public function test_application_import_audits_changed_employees_with_the_admin_actor(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['first_name' => 'Before']);
        $archive = app(ApplicationBackup::class)->export();
        $employeeIndex = collect($archive['data']['employees'])->search(fn (array $row) => $row['id'] === $employee->id);
        $archive['data']['employees'][$employeeIndex]['first_name'] = 'After';
        $file = UploadedFile::fake()->createWithContent('backup.json', json_encode($archive, JSON_THROW_ON_ERROR));

        $this->actingAs($admin)->post('/employee-backup/import', ['file' => $file])->assertOk();

        $event = EmployeeAuditEvent::query()->where('employee_id', $employee->id)->sole();
        $this->assertSame('import_updated', $event->action);
        $this->assertSame('backup_import', $event->source);
        $this->assertSame($admin->name, $event->actor_name);
        $this->assertSame('Before', $event->old_values['first_name']);
        $this->assertSame('After', $event->new_values['first_name']);
    }

    public function test_application_import_rolls_back_when_audit_recording_fails(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'Before']);
        $archive = app(ApplicationBackup::class)->export();
        $employeeIndex = collect($archive['data']['employees'])->search(fn (array $row) => $row['id'] === $employee->id);
        $archive['data']['employees'][$employeeIndex]['first_name'] = 'After';
        $file = UploadedFile::fake()->createWithContent('backup.json', json_encode($archive, JSON_THROW_ON_ERROR));
        $audit = Mockery::mock(EmployeeAuditLogger::class);
        $audit->shouldReceive('recordForEmployeeId')->once()->andThrow(new RuntimeException('Audit failed.'));
        $this->app->instance(EmployeeAuditLogger::class, $audit);

        $this->actingAs($this->admin())->post('/employee-backup/import', ['file' => $file])->assertUnprocessable();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'first_name' => 'Before']);
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

    /** One employee-archive record with no email address. */
    private function emaillessRecord(string $first = 'Jane', string $last = 'Doe', array $overrides = []): array
    {
        return $overrides + [
            'first_name' => $first, 'last_name' => $last, 'email' => null,
            'weekly_hours' => 32, 'weekly_hours_minimum' => null, 'confirmed' => true,
            'business_line' => null, 'competences' => [],
            'recurring_availability' => [], 'holidays' => [], 'availability_questions' => [],
        ];
    }

    public function test_import_creates_an_employee_without_an_email(): void
    {
        $this->actingAs($this->admin())->post('/employee-backup/import', [
            'file' => $this->archive([$this->emaillessRecord()]),
        ])->assertOk()->assertJson(['created' => 1, 'updated' => 0]);

        $employee = Employee::sole();
        $this->assertNull($employee->email);
        $this->assertSame('Jane Doe', $employee->name);
    }

    public function test_import_treats_a_blank_email_like_no_email(): void
    {
        $this->actingAs($this->admin())->post('/employee-backup/import', [
            'file' => $this->archive([$this->emaillessRecord(overrides: ['email' => ''])]),
        ])->assertOk()->assertJson(['created' => 1]);

        $this->assertNull(Employee::sole()->email);
    }

    public function test_import_matches_an_employee_without_an_email_by_name(): void
    {
        $existing = Employee::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => null, 'weekly_hours' => 20]);

        $this->actingAs($this->admin())->post('/employee-backup/import', [
            'file' => $this->archive([$this->emaillessRecord('JANE', 'doe')]),
        ])->assertOk()->assertJson(['created' => 0, 'updated' => 1]);

        $this->assertDatabaseCount('employees', 1);
        $this->assertSame(32, $existing->fresh()->weekly_hours);
    }

    public function test_importing_the_same_archive_twice_creates_no_duplicate(): void
    {
        $file = fn () => $this->archive([$this->emaillessRecord()]);

        $this->actingAs($this->admin())->post('/employee-backup/import', ['file' => $file()])
            ->assertJson(['created' => 1]);
        $this->actingAs($this->admin())->post('/employee-backup/import', ['file' => $file()])
            ->assertJson(['created' => 0, 'updated' => 1]);

        $this->assertDatabaseCount('employees', 1);
    }

    public function test_a_name_match_ignores_employees_that_have_an_email(): void
    {
        $withEmail = Employee::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'weekly_hours' => 20]);

        $this->actingAs($this->admin())->post('/employee-backup/import', [
            'file' => $this->archive([$this->emaillessRecord()]),
        ])->assertOk()->assertJson(['created' => 1, 'updated' => 0]);

        $this->assertDatabaseCount('employees', 2);
        $this->assertSame(20, $withEmail->fresh()->weekly_hours);
    }

    public function test_import_rejects_two_records_with_the_same_name_and_no_email(): void
    {
        $response = $this->actingAs($this->admin())->post('/employee-backup/import', [
            'file' => $this->archive([$this->emaillessRecord('Jane', 'Doe'), $this->emaillessRecord('jane', 'DOE')]),
        ])->assertStatus(422);

        $this->assertSame(__('backup.error.duplicate_name', ['record' => 2]), $response->json('errors.0'));
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_import_still_rejects_an_invalid_email(): void
    {
        $this->actingAs($this->admin())->post('/employee-backup/import', [
            'file' => $this->archive([$this->emaillessRecord(overrides: ['email' => 'not-an-email'])]),
        ])->assertStatus(422);

        $this->assertDatabaseCount('employees', 0);
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

    public function test_the_application_archive_exports_and_restores_a_null_email(): void
    {
        $admin = $this->admin();
        $nomail = Employee::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => null]);
        $withEmail = Employee::factory()->create(['email' => 'kim@example.com']);

        $export = $this->actingAs($admin)->get('/employee-backup/export');
        $archive = json_decode($export->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $exported = collect($archive['data']['employees'])->keyBy('id');
        $this->assertNull($exported[$nomail->id]['email']);
        $this->assertSame('kim@example.com', $exported[$withEmail->id]['email']);

        DB::table('employees')->delete();

        $this->actingAs($admin)->post('/employee-backup/import', [
            'file' => UploadedFile::fake()->createWithContent('backup.json', $export->getContent()),
        ])->assertOk()->assertJsonPath('imported', 2);

        $this->assertNull($nomail->fresh()->email);
        $this->assertSame('kim@example.com', $withEmail->fresh()->email);
    }

    public function test_an_admin_can_export_and_restore_the_complete_application_archive(): void
    {
        $businessLine = BusinessLine::factory()->create(['abbreviation' => 'OPS']);
        $shift = Shift::factory()->create(['name' => 'Early']);
        $employee = Employee::factory()->create(['business_line_id' => $businessLine->id]);
        $user = User::factory()->admin()->create([
            'employee_id' => $employee->id,
            'business_line_id' => $businessLine->id,
        ]);

        $export = $this->actingAs($user)->get('/employee-backup/export');
        $archive = json_decode($export->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(2, $archive['version']);
        $this->assertSame('shiftplanner-business-archive', $archive['format']);
        $this->assertArrayHasKey('employees', $archive['data']);
        $this->assertArrayHasKey('users', $archive['data']);
        $this->assertArrayHasKey('shifts', $archive['data']);

        DB::table('users')->delete();
        DB::table('employees')->delete();
        DB::table('business_lines')->delete();
        DB::table('shifts')->delete();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post('/employee-backup/import', [
                'file' => UploadedFile::fake()->createWithContent(
                    'shiftplanner-backup.json',
                    json_encode($archive, JSON_THROW_ON_ERROR),
                ),
            ]);

        $response->assertOk()->assertJson(['version' => 2]);
        $this->assertDatabaseHas('business_lines', ['id' => $businessLine->id, 'abbreviation' => 'OPS']);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'name' => 'Early']);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'business_line_id' => $businessLine->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'employee_id' => $employee->id]);
    }

    public function test_every_database_table_is_archived_or_explicitly_excluded(): void
    {
        $tables = collect(Schema::getTables())->pluck('name')->sort()->values()->all();

        $export = $this->actingAs($this->admin())->get('/employee-backup/export');
        $archived = array_keys($export->json('data'));

        $this->assertSame(
            [],
            array_values(array_diff($tables, $archived, ApplicationBackup::EXCLUDED_TABLES)),
            'A table is neither in the backup archive nor in ApplicationBackup::EXCLUDED_TABLES.',
        );
        $this->assertSame([], array_values(array_diff($archived, $tables)));
    }

    public function test_application_import_keeps_existing_audit_events(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create();
        $event = EmployeeAuditEvent::create([
            'employee_id' => $employee->id,
            'action' => 'updated',
            'subject_type' => 'employee',
            'subject_id' => $employee->id,
            'source' => 'user',
            'actor_type' => 'user',
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_email' => $admin->email,
            'actor_role' => $admin->role,
            'old_values' => ['weekly_hours' => 24],
            'new_values' => ['weekly_hours' => 28],
        ]);
        $archive = $this->actingAs($admin)->get('/employee-backup/export')->getContent();

        $this->actingAs($admin)->post('/employee-backup/import', [
            'file' => UploadedFile::fake()->createWithContent('shiftplanner-backup.json', $archive),
        ])->assertOk();

        $this->assertDatabaseCount('employee_audit_events', 1);
        $this->assertSame(['weekly_hours' => 24], $event->fresh()->old_values);
        $this->assertSame(['weekly_hours' => 28], $event->fresh()->new_values);
    }

    public function test_import_clears_stale_plan_generation_runs(): void
    {
        $admin = $this->admin();
        $archive = $this->actingAs($admin)->get('/employee-backup/export')->getContent();

        PlanGenerationRun::create(['cycle_start' => '2026-09-14', 'status' => PlanGenerationRun::STATUS_RUNNING]);

        $this->actingAs($admin)->post('/employee-backup/import', [
            'file' => UploadedFile::fake()->createWithContent('shiftplanner-backup.json', $archive),
        ])->assertOk();

        $this->assertDatabaseCount('plan_generation_runs', 0);
    }
}
