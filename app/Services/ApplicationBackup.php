<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ApplicationBackup
{
    public const VERSION = 2;

    public const FORMAT = 'shiftplanner-business-archive';

    /** @var list<string> */
    private const TABLES = [
        'users',
        'business_lines',
        'employees',
        'shifts',
        'workcenters',
        'competences',
        'availability_questions',
        'planning_settings',
        'message_templates',
        'employee_personal_links',
        'employee_holidays',
        'recurring_availabilities',
        'competence_employee',
        'availability_question_employee',
        'employee_workcenter',
        'workcenter_shift',
        'workcenter_shift_capacities',
        'workcenter_shift_date_overrides',
        'planning_rules',
        'shift_assignments',
        'published_weeks',
        'messages',
    ];

    /**
     * Tables the archive leaves out on purpose: framework runtime state,
    * short-lived login links, audit history, and transient plan generation
    * runs.
     *
     * @var list<string>
     */
    public const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'employee_audit_events',
        'job_batches',
        'jobs',
        'login_links',
        'migrations',
        'plan_generation_runs',
        'sessions',
    ];

    /** Excluded tables that an import empties, so no stale rows survive a restore. */
    private const CLEARED_ON_IMPORT = ['plan_generation_runs'];

    /** @return array<string, mixed> */
    public function export(): array
    {
        $data = [];

        foreach (self::TABLES as $table) {
            $data[$table] = DB::table($table)->get()->map(function (object $row): array {
                $values = (array) $row;

                unset($values['remember_token']);

                if (array_key_exists('config', $values) && is_string($values['config'])) {
                    $values['config'] = json_decode($values['config'], true, 512, JSON_THROW_ON_ERROR);
                }

                return $values;
            })->all();
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'options' => [
                'include_password_hashes' => true,
                'include_personal_link_tokens' => true,
                'exclude_transient_login_links' => true,
            ],
            'data' => $data,
        ];
    }

    /** @return array{version: int, imported: int} */
    public function import(array $archive): array
    {
        $errors = $this->validate($archive);

        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }

        DB::transaction(function () use ($archive): void {
            $this->clearTables();
            $data = $archive['data'];

            // Users and business lines reference each other. Insert both with
            // nullable ownership fields first, then restore those fields.
            $this->insertRows('users', $this->withoutFields($data['users'], ['employee_id', 'business_line_id']));
            $this->insertRows('business_lines', $this->withoutFields($data['business_lines'], ['responsible_user_id']));
            $this->insertRows('employees', $data['employees']);
            $this->insertRows('users', $data['users']);
            $this->insertRows('business_lines', $data['business_lines']);

            foreach ([
                'shifts',
                'workcenters',
                'competences',
                'availability_questions',
                'planning_settings',
                'message_templates',
                'employee_personal_links',
                'employee_holidays',
                'recurring_availabilities',
                'competence_employee',
                'availability_question_employee',
                'workcenter_shift',
                'workcenter_shift_capacities',
                'workcenter_shift_date_overrides',
                'planning_rules',
                'shift_assignments',
                'published_weeks',
                'messages',
            ] as $table) {
                $this->insertRows($table, $data[$table]);
            }

            // Archives from before the membership change still carry `mode`.
            $this->insertRows('employee_workcenter', $this->withoutFields($data['employee_workcenter'], ['mode']));

            $this->resetSequences();
        });

        return [
            'version' => self::VERSION,
            'imported' => count($archive['data']['employees']),
        ];
    }

    /** @return list<string> */
    private function validate(array $archive): array
    {
        if (($archive['format'] ?? null) !== self::FORMAT || ($archive['version'] ?? null) !== self::VERSION) {
            return ['The archive format or version is not supported.'];
        }

        if (! isset($archive['data']) || ! is_array($archive['data'])) {
            return ['The archive does not contain application data.'];
        }

        $errors = [];
        foreach (self::TABLES as $table) {
            if (! array_key_exists($table, $archive['data']) || ! is_array($archive['data'][$table])) {
                $errors[] = "The archive is missing the {$table} table.";
            }
        }

        return $errors;
    }

    /** @param list<array<string, mixed>> $rows */
    private function insertRows(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $hasId = array_key_exists('id', $rows[0]);
        if ($hasId) {
            foreach ($rows as $row) {
                if (array_key_exists('config', $row) && is_array($row['config'])) {
                    $row['config'] = json_encode($row['config'], JSON_THROW_ON_ERROR);
                }
                DB::table($table)->updateOrInsert(['id' => $row['id']], $row);
            }

            return;
        }

        DB::table($table)->insert($rows);
    }

    /** @param list<array<string, mixed>> $rows */
    private function withoutFields(array $rows, array $fields): array
    {
        return array_map(fn (array $row): array => array_diff_key($row, array_flip($fields)), $rows);
    }

    /**
     * PostgreSQL does not advance a serial sequence when a row is inserted
     * with an explicit ID, so the next new record would reuse ID 1.
     */
    private function resetSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'id')) {
                continue;
            }

            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM {$table}"
            );
        }
    }

    private function clearTables(): void
    {
        foreach (self::CLEARED_ON_IMPORT as $table) {
            DB::table($table)->delete();
        }

        foreach (array_reverse(self::TABLES) as $table) {
            DB::table($table)->delete();
        }
    }
}
