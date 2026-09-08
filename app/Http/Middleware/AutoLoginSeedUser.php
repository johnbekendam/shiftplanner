<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// Dev convenience only. Triple-gated so a copy-pasted .env can't make this
// fire outside a local, debug-enabled environment: see
// doc/features/auth-baseline/spec.md.
class AutoLoginSeedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()
            && config('auth.auto_login')
            && app()->environment('local')
            && config('app.debug') === true
        ) {
            $user = User::where('email', config('auth.seed_user.email'))->first();

            if ($user) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
