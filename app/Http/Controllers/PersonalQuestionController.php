<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SetsQuestionAnswer;
use App\Models\AvailabilityQuestion;
use App\Models\Employee;
use App\Services\EmployeePersonalLinkService;
use Illuminate\Http\Request;

/**
 * Availability-question answers from the personal page.
 *
 * PROTOTYPE ONLY — token-only, no authentication. See
 * EmployeePersonalLinkService and roadmap phase 2.
 */
class PersonalQuestionController extends Controller
{
    use SetsQuestionAnswer;

    public function __construct(private EmployeePersonalLinkService $links) {}

    public function update(Request $request, string $token, AvailabilityQuestion $question)
    {
        $employee = $this->resolveOrFail($token);

        $this->setAnswer($request, $employee, $question);

        return redirect("/personal/{$token}");
    }

    private function resolveOrFail(string $token): Employee
    {
        return $this->links->resolve($token) ?? abort(404);
    }
}
