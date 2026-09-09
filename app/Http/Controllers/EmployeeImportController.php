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

            if ($employee === null) {
                Employee::create(['name' => $row['name'], 'email' => $row['email']]);
                $created++;
            } elseif ($employee->name !== $row['name']) {
                $employee->update(['name' => $row['name']]);
                $updated++;
            }
        }

        return response()->json(['created' => $created, 'updated' => $updated]);
    }

    /**
     * @return array{0: list<array{name: string, email: string}>, 1: list<string>}
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

            if (count($fields) !== 2) {
                $errors[] = __('import.error.columns', ['line' => $lineNo, 'count' => count($fields)]);

                continue;
            }

            $name = trim((string) $fields[0]);
            $email = trim((string) $fields[1]);

            $check = Validator::make(
                ['name' => $name, 'email' => $email],
                [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255'],
                ]
            );

            if ($check->fails()) {
                if ($check->errors()->has('name')) {
                    $errors[] = __('import.error.name', ['line' => $lineNo]);
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
            $rows[] = ['name' => $name, 'email' => $email];
        }

        if (empty($rows) && empty($errors)) {
            $errors[] = __('import.error.empty');
        }

        return [$rows, $errors];
    }
}
