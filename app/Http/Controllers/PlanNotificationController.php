<?php

namespace App\Http\Controllers;

use App\Services\PlanningNotifier;
use Illuminate\Http\Request;

class PlanNotificationController extends Controller
{
    /** Emails every employee with uninformed published planning. */
    public function store(Request $request, PlanningNotifier $notifier)
    {
        $count = $notifier->queueForUninformed($request->user());

        return back()->with('success', __('planning.flash.sent', ['count' => $count]));
    }
}
