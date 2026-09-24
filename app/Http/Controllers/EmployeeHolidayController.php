<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Services\EmployeeAuditLogger;
use Illuminate\Http\Request;

class EmployeeHolidayController extends Controller
{
    public function __construct(private EmployeeAuditLogger $audit) {}

    public function store(Request $request, Employee $employee)
    {
        $holiday = $employee->holidays()->create($request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]));

        $this->audit->record($employee, 'holiday_created', 'holiday', $holiday->id, [], $holiday->toPayload(), 'user', $request->user());

        return back()->with('success', __('availability.flash.holiday_added'));
    }

    public function destroy(Request $request, Employee $employee, EmployeeHoliday $holiday)
    {
        abort_unless($holiday->employee_id === $employee->id, 404);

        $before = $holiday->toPayload();
        $holiday->delete();

        $this->audit->record($employee, 'holiday_removed', 'holiday', $holiday->id, $before, [], 'user', $request->user());

        return back()->with('success', __('availability.flash.holiday_removed'));
    }
}
