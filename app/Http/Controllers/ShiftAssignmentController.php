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

        $blockReason = $this->eligibility->assignmentBlockReason($employee, $workcenter, $shift, $date);
        if ($blockReason !== null) {
            throw ValidationException::withMessages([
                'employee_id' => __("scheduling.error.{$blockReason}"),
            ]);
        }

        ShiftAssignment::create([
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => $date->toDateString(),
            // A manual placement is protected from a future Generate run by
            // default — a manager can still unfreeze it with the existing
            // Freeze/Unfreeze toggle. Only new assignments; no backfill.
            'fixed' => true,
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
