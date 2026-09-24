<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Services\SchedulingEligibility;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EligibleEmployeeController extends Controller
{
    public function __construct(private readonly SchedulingEligibility $eligibility) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'workcenter_id' => ['required', 'integer', 'exists:workcenters,id'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $workcenter = Workcenter::findOrFail($data['workcenter_id']);
        $shift = Shift::findOrFail($data['shift_id']);
        $date = Carbon::parse($data['date']);
        $weekday = $date->isoWeekday();

        $alreadyAssignedIds = ShiftAssignment::query()
            ->where('workcenter_id', $data['workcenter_id'])
            ->where('shift_id', $shift->id)
            ->whereDate('date', $date)
            ->pluck('employee_id');

        $employees = Employee::query()
            ->where('confirmed', true)
            ->whereNotIn('id', $alreadyAssignedIds)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->reject(fn (Employee $employee) => $this->eligibility->isShiftUnavailableForWorkcenter($employee, $workcenter, $shift)
                || $this->eligibility->isOnHoliday($employee, $date)
                || $this->eligibility->isUnavailable($employee, $weekday, $shift)
                || $this->eligibility->hasOverlap($employee, $date, $shift)
                || $this->eligibility->isWorkcenterIneligible($employee, $workcenter)
                || $this->eligibility->hardCapViolation($employee, $shift, $date) !== null)
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'not_preferred' => $this->eligibility->isNotPreferred($employee, $weekday, $shift),
                'workcenter_not_preferred' => $this->eligibility->isWorkcenterNotPreferred($employee, $workcenter),
            ])
            ->values();

        return response()->json($employees);
    }
}
