<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Competence;
use App\Models\Employee;

trait TogglesCompetence
{
    /** Mark that the employee holds the competence. Idempotent. */
    protected function attachCompetence(Employee $employee, Competence $competence): void
    {
        $employee->competences()->syncWithoutDetaching([$competence->id]);
    }

    /** Clear the competence from the employee. A no-op if it was not set. */
    protected function detachCompetence(Employee $employee, Competence $competence): void
    {
        $employee->competences()->detach($competence->id);
    }
}
