<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TogglesCompetence;
use App\Models\Competence;
use App\Models\Employee;
use App\Services\EmployeeAuditLogger;
use App\Services\EmployeePersonalLinkService;

/**
 * Employee competence checkmarks from the personal page.
 *
 * PROTOTYPE ONLY — token-only, no authentication. See
 * EmployeePersonalLinkService and roadmap phase 2.
 */
class PersonalCompetenceController extends Controller
{
    use TogglesCompetence;

    public function __construct(private EmployeePersonalLinkService $links, private EmployeeAuditLogger $audit) {}

    public function update(string $token, Competence $competence)
    {
        $employee = $this->resolveOrFail($token);
        abort_if($competence->read_only, 403);
        $attached = $employee->competences()->whereKey($competence->id)->exists();
        $this->attachCompetence($employee, $competence);

        if (! $attached) {
            $this->audit->recordPersonal($employee, 'competence_attached', 'competence', $competence->id, ['attached' => false], ['attached' => true]);
        }

        return redirect("/personal/{$token}");
    }

    public function destroy(string $token, Competence $competence)
    {
        $employee = $this->resolveOrFail($token);
        abort_if($competence->read_only, 403);
        $attached = $employee->competences()->whereKey($competence->id)->exists();
        $this->detachCompetence($employee, $competence);

        if ($attached) {
            $this->audit->recordPersonal($employee, 'competence_detached', 'competence', $competence->id, ['attached' => true], ['attached' => false]);
        }

        return redirect("/personal/{$token}");
    }

    private function resolveOrFail(string $token): Employee
    {
        return $this->links->resolve($token) ?? abort(404);
    }
}
