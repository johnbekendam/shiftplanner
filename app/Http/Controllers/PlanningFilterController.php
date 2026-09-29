<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PlanningFilterController extends Controller
{
    /** Stores the workcenters and shifts the current user hides on the planning page. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'hidden_workcenter_ids' => ['present', 'array'],
            'hidden_workcenter_ids.*' => ['integer'],
            'hidden_shift_ids' => ['present', 'array'],
            'hidden_shift_ids.*' => ['integer'],
        ]);

        $request->user()->update(['planning_filter' => [
            'hidden_workcenter_ids' => array_map('intval', $data['hidden_workcenter_ids']),
            'hidden_shift_ids' => array_map('intval', $data['hidden_shift_ids']),
        ]]);

        return response()->noContent();
    }
}
