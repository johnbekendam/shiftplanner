<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\EmployeePersonalLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->route('employee');

        if (! $employee instanceof Employee && is_string($request->route('token'))) {
            $employee = EmployeePersonalLink::query()
                ->where('token', $request->route('token'))
                ->first()
                ?->employee;
        }

        abort_if($employee?->archived_at !== null, 409, __('employees.error.archived'));

        return $next($request);
    }
}