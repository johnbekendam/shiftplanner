<?php

namespace App\Http\Controllers;

use App\Services\EmployeePersonalLinkService;
use App\Services\WhatsNew;
use Illuminate\Http\Request;

class WhatsNewController extends Controller
{
    /** Marks the What's new entries of the current user as seen. See features/whats-new/. */
    public function seen(Request $request, WhatsNew $whatsNew)
    {
        $newest = $whatsNew->for($request->user()->role)[0]['date'] ?? null;

        if ($newest !== null) {
            $request->user()->update(['whats_new_seen_at' => $newest]);
        }

        return response()->noContent();
    }

    /** Marks the What's new entries of an employee as seen, by personal-page token. Also while changes are locked. */
    public function seenByEmployee(string $token, EmployeePersonalLinkService $links, WhatsNew $whatsNew)
    {
        $employee = $links->resolve($token) ?? abort(404);
        $newest = $whatsNew->for('employee')[0]['date'] ?? null;

        if ($newest !== null) {
            $employee->update(['whats_new_seen_at' => $newest]);
        }

        return response()->noContent();
    }
}
