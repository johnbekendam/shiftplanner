<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TogglesWorkcenter;
use App\Models\Employee;
use App\Models\Workcenter;
use Illuminate\Http\Request;

class EmployeeWorkcenterController extends Controller
{
    use TogglesWorkcenter;

    public function update(Request $request, Employee $employee, Workcenter $workcenter)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:hard,soft'],
        ]);

        $this->attachWorkcenter($employee, $workcenter, $data['mode']);

        return back();
    }

    public function destroy(Employee $employee, Workcenter $workcenter)
    {
        $this->detachWorkcenter($employee, $workcenter);

        return back();
    }
}
