<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SetsDateAvailability;
use App\Models\Employee;
use App\Services\EmployeeAuditLogger;
use Illuminate\Http\Request;

class DateAvailabilityController extends Controller
{
    use SetsDateAvailability;

    public function __construct(private EmployeeAuditLogger $audit) {}

    public function update(Request $request, Employee $employee, string $date)
    {
        [$before, $after] = $this->setDate($request, $employee, $date);

        if ($before !== $after) {
            $this->audit->record($employee, 'availability_changed', 'availability_date', null, $before, $after, 'user', $request->user());
        }

        return back();
    }
}
