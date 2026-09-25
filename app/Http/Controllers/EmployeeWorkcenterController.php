<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TogglesWorkcenter;
use App\Models\Employee;
use App\Models\Workcenter;
use App\Services\EmployeeAuditLogger;
use Illuminate\Http\Request;

class EmployeeWorkcenterController extends Controller
{
    use TogglesWorkcenter;

    public function __construct(private EmployeeAuditLogger $audit) {}

    public function update(Request $request, Employee $employee, Workcenter $workcenter)
    {
        $attached = $employee->workcenters()->whereKey($workcenter->id)->exists();
        $this->attachWorkcenter($employee, $workcenter);

        if (! $attached) {
            $this->audit->record($employee, 'workcenter_attached', 'workcenter', $workcenter->id, ['attached' => false], ['attached' => true], 'user', $request->user());
        }

        return back();
    }

    public function destroy(Request $request, Employee $employee, Workcenter $workcenter)
    {
        $attached = $employee->workcenters()->whereKey($workcenter->id)->exists();
        $this->detachWorkcenter($employee, $workcenter);

        if ($attached) {
            $this->audit->record($employee, 'workcenter_detached', 'workcenter', $workcenter->id, ['attached' => true], ['attached' => false], 'user', $request->user());
        }

        return back();
    }
}
