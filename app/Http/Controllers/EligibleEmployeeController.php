<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
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

        $shift = Shift::findOrFail($data['shift_id']);
        $date = Carbon::parse($data['date']);
        $weekday = $date->isoWeekday();

        $alreadyAssignedIds = ShiftAssignment::query()
            ->where('workcenter_id', $data['workcenter_id'])
            ->where('shift_id', $shift->id)
            ->whereDate('date', $date)
            ->pluck('employee_id');

        $employees = Employee::query()
            ->whereNotIn('id', $alreadyAssignedIds)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->reject(fn (Employee $employee) => $this->eligibility->isOnHoliday($employee, $date)
                || $this->eligibility->isUnavailable($employee, $weekday, $shift)
                || $this->eligibility->hasOverlap($employee, $date, $shift))
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'not_preferred' => $this->eligibility->isNotPreferred($employee, $weekday, $shift),
            ])
            ->values();

        return response()->json($employees);
    }
}
