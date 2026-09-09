<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('EmployeeImport');
    }

    public function store(Request $request): JsonResponse
    {
        $upload = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        if ($upload->fails()) {
            return response()->json(['errors' => [__('import.error.file')]], 422);
        }

        [$rows, $errors] = $this->parse($request->file('file')->get());

        if (! empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }

        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $employee = Employee::firstWhere('email', $row['email']);

            $names = ['first_name' => $row['first_name'], 'last_name' => $row['last_name']];

            if ($employee === null) {
                Employee::create([...$names, 'email' => $row['email']]);
                $created++;
            } elseif ($employee->first_name !== $row['first_name'] || $employee->last_name !== $row['last_name']) {
                $employee->update($names);
                $updated++;
            }
        }

        return response()->json(['created' => $created, 'updated' => $updated]);
    }

    /**
     * @return array{0: list<array{first_name: string, last_name: string, email: string}>, 1: list<string>}
     */
    private function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\r|\n/', $contents);

        // The first line is always the header, whatever it contains.
        $header = array_shift($lines) ?? '';
        $delimiter = substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';

        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $lineNo = $index + 2;
            $fields = str_getcsv($line, $delimiter, '"', '\\');

            if (count($fields) !== 3) {
                $errors[] = __('import.error.columns', ['line' => $lineNo, 'count' => count($fields)]);

                continue;
            }

            $firstName = trim((string) $fields[0]);
            $lastName = trim((string) $fields[1]);
            $email = trim((string) $fields[2]);

            $check = Validator::make(
                ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email],
                [
                    'first_name' => ['required', 'string', 'max:255'],
                    'last_name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255'],
                ]
            );

            if ($check->fails()) {
                if ($check->errors()->has('first_name')) {
                    $errors[] = __('import.error.first_name', ['line' => $lineNo]);
                }
                if ($check->errors()->has('last_name')) {
                    $errors[] = __('import.error.last_name', ['line' => $lineNo]);
                }
                if ($check->errors()->has('email')) {
                    $errors[] = __('import.error.email', ['line' => $lineNo]);
                }

                continue;
            }

            $key = mb_strtolower($email);

            if (isset($seen[$key])) {
                $errors[] = __('import.error.duplicate', ['line' => $lineNo, 'first' => $seen[$key], 'email' => $email]);

                continue;
            }

            $seen[$key] = $lineNo;
            $rows[] = ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email];
        }

        if (empty($rows) && empty($errors)) {
            $errors[] = __('import.error.empty');
        }

        return [$rows, $errors];
    }
}
