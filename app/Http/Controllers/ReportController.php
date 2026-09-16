<?php

namespace App\Http\Controllers;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $shiftId = $request->integer('shift') ?: null;
        $businessLineId = $request->integer('business_line') ?: null;
        $includeUnconfirmed = $request->boolean('unconfirmed');

        return Inertia::render('Reports/Index', [
            'employees' => $shiftId === null ? [] : $this->missingAvailability(
                $shiftId,
                $businessLineId,
                $includeUnconfirmed,
            ),
            'shifts' => Shift::all()->map->toPayload()->all(),
            'businessLines' => BusinessLine::all()->map->toPayload()->all(),
            'filters' => [
                'shift' => $shiftId,
                'business_line' => $businessLineId,
                'unconfirmed' => $includeUnconfirmed,
            ],
        ]);
    }

    /** Employees with no recurring-availability row at all for the given shift. */
    private function missingAvailability(int $shiftId, ?int $businessLineId, bool $includeUnconfirmed): array
    {
        return Employee::query()
            ->where('weekly_hours', '>', 0)
            ->when(!$includeUnconfirmed, fn ($q) => $q->where('confirmed', true))
            ->when($businessLineId !== null, fn ($q) => $q->where('business_line_id', $businessLineId))
            ->whereDoesntHave('recurringAvailabilities', fn ($q) => $q->where('shift_id', $shiftId))
            ->with('businessLine')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'business_line' => $employee->businessLine?->abbreviation,
                'weekly_hours' => $employee->weekly_hours,
                'confirmed' => $employee->confirmed,
            ])
            ->all();
    }
}
