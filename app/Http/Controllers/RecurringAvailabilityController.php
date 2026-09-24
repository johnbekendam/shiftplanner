<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SetsRecurringAvailability;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\EmployeeAuditLogger;
use Illuminate\Http\Request;

class RecurringAvailabilityController extends Controller
{
    use SetsRecurringAvailability;

    public function __construct(private EmployeeAuditLogger $audit) {}

    public function update(Request $request, Employee $employee, int $weekday, Shift $shift)
    {
        $beforeModel = $employee->recurringAvailabilities()->where('weekday', $weekday)->where('shift_id', $shift->id)->first();
        $before = $beforeModel?->toPayload() ?? [];
        $this->setCell($request, $employee, $weekday, $shift);
        $afterModel = $employee->recurringAvailabilities()->where('weekday', $weekday)->where('shift_id', $shift->id)->first();
        $after = $afterModel?->toPayload() ?? [];

        if ($before !== $after) {
            $this->audit->record(
                $employee,
                'availability_changed',
                'recurring_availability',
                $afterModel?->id ?? $beforeModel?->id,
                $before,
                $after,
                'user',
                $request->user(),
            );
        }

        return back();
    }
}
