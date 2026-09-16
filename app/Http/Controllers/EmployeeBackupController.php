<?php

namespace App\Http\Controllers;

use App\Models\AvailabilityQuestion;
use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeBackupController extends Controller
{
    private const VERSION = 1;

    public function index(): Response
    {
        return Inertia::render('EmployeeBackup');
    }

    public function export(): JsonResponse
    {
        $employees = Employee::query()
            ->with(['businessLine', 'competences', 'recurringAvailabilities.shift', 'holidays', 'availabilityQuestions'])
            ->orderBy('id')
            ->get()
            ->map(fn (Employee $employee) => $this->exportEmployee($employee))
            ->all();

        return response()->json(['version' => self::VERSION, 'employees' => $employees])
            ->header('Content-Disposition', 'attachment; filename=employees-backup.json');
    }

    public function import(Request $request): JsonResponse
    {
        $upload = Validator::make($request->all(), ['file' => ['required', 'file', 'max:2048']]);

        if ($upload->fails()) {
            return response()->json(['errors' => [__('backup.error.file')]], 422);
        }

        try {
            $archive = json_decode($request->file('file')->get(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json(['errors' => [__('backup.error.invalid_json')]], 422);
        }

        $errors = $this->validateArchive($archive);

        if ($errors !== []) {
            return response()->json(['errors' => $errors], 422);
        }

        [$created, $updated] = DB::transaction(function () use ($archive): array {
            $created = 0;
            $updated = 0;

            foreach ($archive['employees'] as $record) {
                $employee = Employee::firstWhere('email', $record['email']);
                $isNew = $employee === null;
                $employee ??= new Employee;

                $employee->fill([
                    'first_name' => $record['first_name'],
                    'last_name' => $record['last_name'],
                    'email' => $record['email'],
                    'weekly_hours' => $record['weekly_hours'],
                    'weekly_hours_minimum' => $record['weekly_hours_minimum'],
                    'confirmed' => $record['confirmed'],
                    'business_line_id' => $this->businessLineId($record['business_line']),
                ]);
                $employee->save();

                $employee->competences()->sync(collect($record['competences'])
                    ->map(fn (array $competence) => $this->competenceId($competence))
                    ->all());
                $employee->availabilityQuestions()->sync(collect($record['availability_questions'])
                    ->map(fn (array $question) => AvailabilityQuestion::where('text', $question['text'])->value('id'))
                    ->all());
                $employee->recurringAvailabilities()->delete();
                $employee->holidays()->delete();

                foreach ($record['recurring_availability'] as $availability) {
                    $employee->recurringAvailabilities()->create([
                        'weekday' => $availability['weekday'],
                        'shift_id' => $this->shiftId($availability['shift']),
                        'level' => $availability['level'],
                    ]);
                }

                foreach ($record['holidays'] as $holiday) {
                    $employee->holidays()->create($holiday);
                }

                $isNew ? $created++ : $updated++;
            }

            return [$created, $updated];
        });

        return response()->json(['created' => $created, 'updated' => $updated]);
    }

    private function exportEmployee(Employee $employee): array
    {
        return [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'weekly_hours' => $employee->weekly_hours,
            'weekly_hours_minimum' => $employee->weekly_hours_minimum,
            'confirmed' => $employee->confirmed,
            'business_line' => $employee->businessLine === null ? null : [
                'abbreviation' => $employee->businessLine->abbreviation,
                'description' => $employee->businessLine->description,
                'target_fte' => $employee->businessLine->target_fte,
            ],
            'competences' => $employee->competences->map(fn (Competence $competence) => [
                'name' => $competence->name,
                'read_only' => $competence->read_only,
            ])->values()->all(),
            'recurring_availability' => $employee->recurringAvailabilities->map(fn ($availability) => [
                'weekday' => $availability->weekday,
                'level' => $availability->level,
                'shift' => [
                    'name' => $availability->shift->name,
                    'start_time' => $availability->shift->start_time,
                    'end_time' => $availability->shift->end_time,
                ],
            ])->values()->all(),
            'holidays' => $employee->holidays->map(fn ($holiday) => [
                'start_date' => $holiday->start_date->toDateString(),
                'end_date' => $holiday->end_date->toDateString(),
                'note' => $holiday->note,
            ])->values()->all(),
            'availability_questions' => $employee->availabilityQuestions->map(fn (AvailabilityQuestion $question) => [
                'text' => $question->text,
            ])->values()->all(),
        ];
    }

    /** @return list<string> */
    private function validateArchive(mixed $archive): array
    {
        if (! is_array($archive) || ($archive['version'] ?? null) !== self::VERSION || ! isset($archive['employees']) || ! is_array($archive['employees'])) {
            return [__('backup.error.schema')];
        }

        $errors = [];
        $seenEmails = [];

        foreach ($archive['employees'] as $index => $employee) {
            $line = $index + 1;
            $validator = Validator::make($employee, [
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'weekly_hours' => ['required', 'integer', 'min:0', 'max:48'],
                'weekly_hours_minimum' => ['nullable', 'integer', 'min:0'],
                'confirmed' => ['required', 'boolean'],
                'business_line' => ['nullable', 'array'],
                'business_line.abbreviation' => ['required_with:business_line', 'string', 'max:10'],
                'business_line.description' => ['required_with:business_line', 'string', 'max:255'],
                'business_line.target_fte' => ['nullable', 'numeric', 'min:0'],
                'competences' => ['present', 'array'],
                'competences.*.name' => ['required', 'string', 'max:100'],
                'competences.*.read_only' => ['required', 'boolean'],
                'recurring_availability' => ['present', 'array'],
                'recurring_availability.*.weekday' => ['required', 'integer', 'between:1,5'],
                'recurring_availability.*.level' => ['required', 'in:available,not_preferred,unavailable'],
                'recurring_availability.*.shift.name' => ['required', 'string'],
                'recurring_availability.*.shift.start_time' => ['required', 'date_format:H:i'],
                'recurring_availability.*.shift.end_time' => ['required', 'date_format:H:i'],
                'holidays' => ['present', 'array'],
                'holidays.*.start_date' => ['required', 'date_format:Y-m-d'],
                'holidays.*.end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:holidays.*.start_date'],
                'holidays.*.note' => ['nullable', 'string', 'max:255'],
                'availability_questions' => ['present', 'array'],
                'availability_questions.*.text' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                $errors[] = __('backup.error.employee', [
                    'record' => $line,
                    'fields' => implode(', ', $validator->errors()->keys()),
                ]);

                continue;
            }

            $email = mb_strtolower($employee['email']);
            if (isset($seenEmails[$email])) {
                $errors[] = __('backup.error.duplicate_email', ['record' => $line]);
            }
            $seenEmails[$email] = true;

            if ($employee['business_line'] !== null && $this->businessLineId($employee['business_line']) === null) {
                $errors[] = __('backup.error.business_line', [
                    'record' => $line,
                    'abbreviation' => $employee['business_line']['abbreviation'],
                ]);
            }
            foreach ($employee['competences'] as $competence) {
                if ($this->competenceId($competence) === null) {
                    $errors[] = __('backup.error.competence', ['record' => $line, 'name' => $competence['name']]);
                }
            }
            foreach ($employee['recurring_availability'] as $availability) {
                if ($this->shiftId($availability['shift']) === null) {
                    $errors[] = __('backup.error.shift', [
                        'record' => $line,
                        'shift' => "{$availability['shift']['name']} ({$availability['shift']['start_time']}-{$availability['shift']['end_time']})",
                    ]);
                }
            }
            foreach ($employee['availability_questions'] as $question) {
                if (! AvailabilityQuestion::where('text', $question['text'])->exists()) {
                    $errors[] = __('backup.error.question', ['record' => $line, 'text' => $question['text']]);
                }
            }
        }

        return $errors;
    }

    private function businessLineId(?array $businessLine): ?int
    {
        if ($businessLine === null) {
            return null;
        }

        return BusinessLine::query()
            ->whereRaw('lower(abbreviation) = ?', [mb_strtolower($businessLine['abbreviation'])])
            ->value('id');
    }

    private function competenceId(array $competence): ?int
    {
        return Competence::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($competence['name'])])
            ->value('id');
    }

    private function shiftId(array $shift): ?int
    {
        return Shift::query()
            ->where('name', $shift['name'])
            ->where('start_time', $shift['start_time'])
            ->where('end_time', $shift['end_time'])
            ->value('id');
    }
}
