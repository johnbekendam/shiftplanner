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
        $data = $request->validate([
            'mode' => ['required', 'in:hard,soft'],
        ]);

        $before = $employee->workcenters()->whereKey($workcenter->id)->first()?->pivot?->mode;
        $this->attachWorkcenter($employee, $workcenter, $data['mode']);

        if ($before !== $data['mode']) {
            $this->audit->record($employee, 'workcenter_changed', 'workcenter', $workcenter->id, ['mode' => $before], ['mode' => $data['mode']], 'user', $request->user());
        }

        return back();
    }

    public function destroy(Request $request, Employee $employee, Workcenter $workcenter)
    {
        $before = $employee->workcenters()->whereKey($workcenter->id)->first()?->pivot?->mode;
        $this->detachWorkcenter($employee, $workcenter);

        if ($before !== null) {
            $this->audit->record($employee, 'workcenter_detached', 'workcenter', $workcenter->id, ['mode' => $before], ['mode' => null], 'user', $request->user());
        }

        return back();
    }
}
