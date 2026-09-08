<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SetsRecurringAvailability;
use App\Models\Employee;
use App\Services\EmployeePersonalLinkService;
use Illuminate\Http\Request;

/**
 * Recurring availability grid edits from the personal page.
 *
 * PROTOTYPE ONLY — token-only, no authentication. See
 * EmployeePersonalLinkService and roadmap phase 2.
 */
class PersonalRecurringAvailabilityController extends Controller
{
    use SetsRecurringAvailability;

    public function __construct(private EmployeePersonalLinkService $links) {}

    public function update(Request $request, string $token, int $weekday, string $daypart)
    {
        $employee = $this->resolveOrFail($token);

        $this->setCell($request, $employee, $weekday, $daypart);

        return redirect("/personal/{$token}");
    }

    private function resolveOrFail(string $token): Employee
    {
        return $this->links->resolve($token) ?? abort(404);
    }
}
