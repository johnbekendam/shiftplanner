<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SetsRecurringAvailability;
use App\Models\Employee;
use Illuminate\Http\Request;

class RecurringAvailabilityController extends Controller
{
    use SetsRecurringAvailability;

    public function update(Request $request, Employee $employee, int $weekday, string $daypart)
    {
        $this->setCell($request, $employee, $weekday, $daypart);

        return back();
    }
}
