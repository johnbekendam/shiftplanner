<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AvailabilityQuestion;
use App\Models\Employee;
use Illuminate\Http\Request;

trait SetsQuestionAnswer
{
    /**
     * Record the employee's yes/no answer to one question. A yes upserts
     * the single pivot row; a no clears it. Idempotent either way.
     */
    protected function setAnswer(Request $request, Employee $employee, AvailabilityQuestion $question): void
    {
        $answer = $request->validate([
            'answer' => ['required', 'boolean'],
        ])['answer'];

        if ($answer) {
            $employee->availabilityQuestions()->syncWithoutDetaching([$question->id]);

            return;
        }

        $employee->availabilityQuestions()->detach($question->id);
    }
}
