<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TogglesCompetence;
use App\Models\Competence;
use App\Models\Employee;
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

    public function __construct(private EmployeePersonalLinkService $links) {}

    public function update(string $token, Competence $competence)
    {
        $this->attachCompetence($this->resolveOrFail($token), $competence);

        return redirect("/personal/{$token}");
    }

    public function destroy(string $token, Competence $competence)
    {
        $this->detachCompetence($this->resolveOrFail($token), $competence);

        return redirect("/personal/{$token}");
    }

    private function resolveOrFail(string $token): Employee
    {
        return $this->links->resolve($token) ?? abort(404);
    }
}
