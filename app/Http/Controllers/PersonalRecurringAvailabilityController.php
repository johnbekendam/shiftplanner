<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SetsRecurringAvailability;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\EmployeeAuditLogger;
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

    public function __construct(private EmployeePersonalLinkService $links, private EmployeeAuditLogger $audit) {}

    public function update(Request $request, string $token, int $weekday, Shift $shift)
    {
        $employee = $this->resolveOrFail($token);

        $beforeModel = $employee->recurringAvailabilities()->where('weekday', $weekday)->where('shift_id', $shift->id)->first();
        $before = $beforeModel?->toPayload() ?? [];
        $this->setCell($request, $employee, $weekday, $shift);
        $afterModel = $employee->recurringAvailabilities()->where('weekday', $weekday)->where('shift_id', $shift->id)->first();
        $after = $afterModel?->toPayload() ?? [];

        if ($before !== $after) {
            $this->audit->recordPersonal($employee, 'availability_changed', 'recurring_availability', $afterModel?->id ?? $beforeModel?->id, $before, $after);
        }

        return redirect("/personal/{$token}");
    }

    private function resolveOrFail(string $token): Employee
    {
        return $this->links->resolve($token) ?? abort(404);
    }
}
