<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Employee;
use App\Models\Workcenter;

trait TogglesWorkcenter
{
    /** Attach the workcenter with the given mode, or update its mode if already attached. */
    protected function attachWorkcenter(Employee $employee, Workcenter $workcenter, string $mode): void
    {
        $employee->workcenters()->syncWithoutDetaching([$workcenter->id => ['mode' => $mode]]);
    }

    /** Clear the workcenter from the employee. A no-op if it was not set. */
    protected function detachWorkcenter(Employee $employee, Workcenter $workcenter): void
    {
        $employee->workcenters()->detach($workcenter->id);
    }
}
