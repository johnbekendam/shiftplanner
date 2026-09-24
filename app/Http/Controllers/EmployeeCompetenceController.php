<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TogglesCompetence;
use App\Models\Competence;
use App\Models\Employee;
use App\Services\EmployeeAuditLogger;
use Illuminate\Http\Request;

class EmployeeCompetenceController extends Controller
{
    use TogglesCompetence;

    public function __construct(private EmployeeAuditLogger $audit) {}

    public function update(Request $request, Employee $employee, Competence $competence)
    {
        $attached = $employee->competences()->whereKey($competence->id)->exists();
        $this->attachCompetence($employee, $competence);

        if (! $attached) {
            $this->audit->record($employee, 'competence_attached', 'competence', $competence->id, ['attached' => false], ['attached' => true], 'user', $request->user());
        }

        return back();
    }

    public function destroy(Request $request, Employee $employee, Competence $competence)
    {
        $attached = $employee->competences()->whereKey($competence->id)->exists();
        $this->detachCompetence($employee, $competence);

        if ($attached) {
            $this->audit->record($employee, 'competence_detached', 'competence', $competence->id, ['attached' => true], ['attached' => false], 'user', $request->user());
        }

        return back();
    }
}
