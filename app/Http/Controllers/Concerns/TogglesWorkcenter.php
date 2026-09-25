<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Employee;
use App\Models\Workcenter;

trait TogglesWorkcenter
{
    /** Make the employee a member of the workcenter. A no-op if already a member. */
    protected function attachWorkcenter(Employee $employee, Workcenter $workcenter): void
    {
        $employee->workcenters()->syncWithoutDetaching([$workcenter->id]);
    }

    /** Clear the workcenter from the employee. A no-op if it was not set. */
    protected function detachWorkcenter(Employee $employee, Workcenter $workcenter): void
    {
        $employee->workcenters()->detach($workcenter->id);
    }
}
