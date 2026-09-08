<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TogglesCompetence;
use App\Models\Competence;
use App\Models\Employee;

class EmployeeCompetenceController extends Controller
{
    use TogglesCompetence;

    public function update(Employee $employee, Competence $competence)
    {
        $this->attachCompetence($employee, $competence);

        return back();
    }

    public function destroy(Employee $employee, Competence $competence)
    {
        $this->detachCompetence($employee, $competence);

        return back();
    }
}
