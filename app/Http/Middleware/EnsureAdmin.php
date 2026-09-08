<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-only route guard. Runs after `auth`, so the user is always
 * present here; a non-admin is a 403.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return $next($request);
    }
}
