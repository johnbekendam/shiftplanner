<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Services\EmployeePersonalLinkService;
use Illuminate\Http\Request;

/**
 * Holiday add/delete from the employee personal page.
 *
 * PROTOTYPE ONLY — token-only, no authentication. See
 * EmployeePersonalLinkService and roadmap phase 2.
 */
class PersonalHolidayController extends Controller
{
    public function __construct(private EmployeePersonalLinkService $links) {}

    public function store(Request $request, string $token)
    {
        $employee = $this->resolveOrFail($token);

        $employee->holidays()->create($request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]));

        return redirect("/personal/{$token}")->with('success', __('availability.flash.holiday_added'));
    }

    public function destroy(string $token, EmployeeHoliday $holiday)
    {
        $employee = $this->resolveOrFail($token);

        abort_unless($holiday->employee_id === $employee->id, 404);

        $holiday->delete();

        return redirect("/personal/{$token}")->with('success', __('availability.flash.holiday_removed'));
    }

    private function resolveOrFail(string $token): Employee
    {
        return $this->links->resolve($token) ?? abort(404);
    }
}
