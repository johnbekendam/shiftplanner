<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use Illuminate\Http\Request;

class EmployeeHolidayController extends Controller
{
    public function store(Request $request, Employee $employee)
    {
        $employee->holidays()->create($request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('success', __('availability.flash.holiday_added'));
    }

    public function destroy(Employee $employee, EmployeeHoliday $holiday)
    {
        abort_unless($holiday->employee_id === $employee->id, 404);

        $holiday->delete();

        return back()->with('success', __('availability.flash.holiday_removed'));
    }
}
