<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeePersonalLink;
use Illuminate\Support\Str;

/**
 * Builds and resolves employee personal-page links.
 *
 * PROTOTYPE ONLY. The token is opaque but stored in plain text and never
 * expires. It is a navigation aid for a realistic URL, not an
 * authentication mechanism. Roadmap phase 2 replaces it with hashed,
 * cryptographically random, expiring, revocable tokens behind a dedicated
 * employee guard.
 */
class EmployeePersonalLinkService
{
    /** Get or create the employee's link and return its absolute URL. */
    public function linkFor(Employee $employee): string
    {
        $link = $employee->personalLink()->firstOrCreate(
            [],
            ['token' => Str::random(40)],
        );

        return url("/personal/{$link->token}");
    }

    /** Resolve a token to its employee, or null when it does not match. */
    public function resolve(string $token): ?Employee
    {
        return EmployeePersonalLink::where('token', $token)->first()?->employee;
    }
}
