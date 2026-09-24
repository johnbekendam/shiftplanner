<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SetsQuestionAnswer;
use App\Models\AvailabilityQuestion;
use App\Models\Employee;
use App\Services\EmployeeAuditLogger;
use Illuminate\Http\Request;

class EmployeeQuestionController extends Controller
{
    use SetsQuestionAnswer;

    public function __construct(private EmployeeAuditLogger $audit) {}

    public function update(Request $request, Employee $employee, AvailabilityQuestion $question)
    {
        $before = $employee->availabilityQuestions()->whereKey($question->id)->exists();
        $this->setAnswer($request, $employee, $question);
        $after = $employee->availabilityQuestions()->whereKey($question->id)->exists();

        if ($before !== $after) {
            $this->audit->record($employee, 'question_answer_changed', 'question', $question->id, ['answer' => $before], ['answer' => $after], 'user', $request->user());
        }

        return back();
    }
}
