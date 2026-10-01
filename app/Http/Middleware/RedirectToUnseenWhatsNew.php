<?php

namespace App\Http\Middleware;

use App\Services\WhatsNew;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On an entry point of the app (the dashboard, where `/` and the login go),
 * sends a user with unseen entries to What's new first. The visit there
 * marks them seen, so this happens once per update. See features/whats-new/.
 */
class RedirectToUnseenWhatsNew
{
    public function __construct(private WhatsNew $whatsNew) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $this->whatsNew->unseenCount($user->role, $user->whats_new_seen_at?->toDateString()) > 0) {
            return redirect('/whats-new');
        }

        return $next($request);
    }
}
