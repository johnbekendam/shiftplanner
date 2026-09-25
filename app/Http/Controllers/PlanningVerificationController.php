<?php

namespace App\Http\Controllers;

use App\Services\PlanningVerifier;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PlanningVerificationController extends Controller
{
    public function __construct(private readonly PlanningVerifier $verifier) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'violations' => $this->verifier->verifyWeek(Carbon::parse($data['week_start'])),
        ]);
    }
}
