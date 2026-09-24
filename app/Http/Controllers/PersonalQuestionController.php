<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SetsQuestionAnswer;
use App\Models\AvailabilityQuestion;
use App\Models\Employee;
use App\Services\EmployeeAuditLogger;
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

    public function __construct(private EmployeePersonalLinkService $links, private EmployeeAuditLogger $audit) {}

    public function update(Request $request, string $token, AvailabilityQuestion $question)
    {
        $employee = $this->resolveOrFail($token);

        $before = $employee->availabilityQuestions()->whereKey($question->id)->exists();
        $this->setAnswer($request, $employee, $question);
        $after = $employee->availabilityQuestions()->whereKey($question->id)->exists();

        if ($before !== $after) {
            $this->audit->recordPersonal($employee, 'question_answer_changed', 'question', $question->id, ['answer' => $before], ['answer' => $after]);
        }

        return redirect("/personal/{$token}");
    }

    private function resolveOrFail(string $token): Employee
    {
        return $this->links->resolve($token) ?? abort(404);
    }
}
