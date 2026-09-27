<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Shift;
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

        $employees = Employee::query()
            ->active()
            ->where('confirmed', true)
            // Only what can touch this date: the holidays spanning it, its overrides.
            ->with([
                'holidays' => fn ($q) => $q->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date),
                'recurringAvailabilities',
                'availabilityOverrides' => fn ($q) => $q->whereDate('date', $date),
            ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'block_reason' => $this->eligibility->assignmentBlockReason($employee, $workcenter, $shift, $date),
                'not_preferred' => $this->eligibility->isNotPreferred($employee, $date, $shift),
            ])
            ->values();

        return response()->json($employees);
    }
}
