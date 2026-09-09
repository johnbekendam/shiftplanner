<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SetsRecurringAvailability;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Http\Request;

class RecurringAvailabilityController extends Controller
{
    use SetsRecurringAvailability;

    public function update(Request $request, Employee $employee, int $weekday, Shift $shift)
    {
        $this->setCell($request, $employee, $weekday, $shift);

        return back();
    }
}
