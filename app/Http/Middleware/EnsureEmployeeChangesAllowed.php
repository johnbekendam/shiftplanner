<?php

namespace App\Http\Middleware;

use App\Models\PlanningSettings;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks employee-side writes on the personal page when a manager has
 * turned off `allow_employee_changes`. The personal page itself
 * (`personal.show`) is deliberately not guarded, so it stays viewable
 * read-only. Manager routes never carry this middleware.
 */
class EnsureEmployeeChangesAllowed
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(PlanningSettings::current()->allow_employee_changes, 403);

        return $next($request);
    }
}
