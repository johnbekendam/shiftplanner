<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Services\SchedulingEligibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftAssignmentController extends Controller
{
    public function __construct(private readonly SchedulingEligibility $eligibility) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'workcenter_id' => ['required', 'integer', 'exists:workcenters,id'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $workcenter = Workcenter::findOrFail($data['workcenter_id']);
        $shift = Shift::findOrFail($data['shift_id']);
        $date = Carbon::parse($data['date']);

        $existing = ShiftAssignment::query()
            ->where('workcenter_id', $workcenter->id)
            ->where('shift_id', $shift->id)
            ->whereDate('date', $date);

        if ((clone $existing)->where('employee_id', $employee->id)->exists()) {
            throw ValidationException::withMessages(['employee_id' => __('scheduling.error.duplicate')]);
        }

        if ($existing->count() >= $workcenter->spotsFor($shift, $date)) {
            throw ValidationException::withMessages(['employee_id' => __('scheduling.error.cell_full')]);
        }

        if (! $this->eligibility->isShiftVisible($employee, $shift)) {
            throw ValidationException::withMessages(['employee_id' => __('scheduling.error.shift_hidden')]);
        }

        if ($this->eligibility->isOnHoliday($employee, $date)) {
            throw ValidationException::withMessages(['employee_id' => __('scheduling.error.holiday')]);
        }

        if ($this->eligibility->isUnavailable($employee, $date->isoWeekday(), $shift)) {
            throw ValidationException::withMessages(['employee_id' => __('scheduling.error.unavailable')]);
        }

        if ($this->eligibility->hasOverlap($employee, $date, $shift)) {
            throw ValidationException::withMessages(['employee_id' => __('scheduling.error.overlap')]);
        }

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => $date->toDateString(),
            'fixed' => false,
        ]);

        return back()->with('success', __('scheduling.flash.assigned'));
    }

    public function updateFixed(Request $request, ShiftAssignment $shiftAssignment)
    {
        $data = $request->validate(['fixed' => ['required', 'boolean']]);

        $shiftAssignment->update(['fixed' => $data['fixed']]);

        return back()->with('success', __('scheduling.flash.updated'));
    }

    public function destroy(ShiftAssignment $shiftAssignment)
    {
        $shiftAssignment->delete();

        return back()->with('success', __('scheduling.flash.removed'));
    }
}
