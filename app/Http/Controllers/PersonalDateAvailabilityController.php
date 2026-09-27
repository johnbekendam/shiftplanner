<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SetsDateAvailability;
use App\Models\Employee;
use App\Services\EmployeeAuditLogger;
use App\Services\EmployeePersonalLinkService;
use Illuminate\Http\Request;

/**
 * Date-specific availability edits from the personal page.
 *
 * PROTOTYPE ONLY — token-only, no authentication. See
 * EmployeePersonalLinkService and roadmap phase 2.
 */
class PersonalDateAvailabilityController extends Controller
{
    use SetsDateAvailability;

    public function __construct(private EmployeePersonalLinkService $links, private EmployeeAuditLogger $audit) {}

    public function update(Request $request, string $token, string $date)
    {
        $employee = $this->resolveOrFail($token);

        [$before, $after] = $this->setDate($request, $employee, $date);

        if ($before !== $after) {
            $this->audit->recordPersonal($employee, 'availability_changed', 'availability_date', null, $before, $after);
        }

        return redirect("/personal/{$token}");
    }

    private function resolveOrFail(string $token): Employee
    {
        return $this->links->resolve($token) ?? abort(404);
    }
}
