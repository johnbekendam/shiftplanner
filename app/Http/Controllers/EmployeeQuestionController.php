<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SetsQuestionAnswer;
use App\Models\AvailabilityQuestion;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeQuestionController extends Controller
{
    use SetsQuestionAnswer;

    public function update(Request $request, Employee $employee, AvailabilityQuestion $question)
    {
        $this->setAnswer($request, $employee, $question);

        return back();
    }
}
