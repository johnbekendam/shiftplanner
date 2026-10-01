<?php

namespace App\Http\Controllers;

use App\Services\WhatsNew;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WhatsNewController extends Controller
{
    /**
     * Every entry of the user, the ones that were unseen flagged as new. The
     * visit marks them all as seen. See features/whats-new/.
     */
    public function index(Request $request, WhatsNew $whatsNew)
    {
        $user = $request->user();
        $seenAt = $user->whats_new_seen_at?->toDateString();
        $entries = collect($whatsNew->for($user->role))
            ->map(fn (array $entry) => [...$entry, 'new' => $seenAt === null || $entry['date'] > $seenAt])
            ->all();

        if ($entries !== []) {
            $user->update(['whats_new_seen_at' => $entries[0]['date']]);
        }

        $requested = $request->query('entry');

        return Inertia::render('WhatsNew', [
            'entries' => $entries,
            'selectedId' => collect($entries)->contains('id', $requested) ? $requested : ($entries[0]['id'] ?? null),
        ]);
    }

    /** Marks the What's new entries of the current user as seen. See features/whats-new/. */
    public function seen(Request $request, WhatsNew $whatsNew)
    {
        $newest = $whatsNew->for($request->user()->role)[0]['date'] ?? null;

        if ($newest !== null) {
            $request->user()->update(['whats_new_seen_at' => $newest]);
        }

        return response()->noContent();
    }
}
