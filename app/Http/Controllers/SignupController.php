<?php

namespace App\Http\Controllers;

use App\Services\SelfSignupService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Public employee self-signup: a person requests the link to their own
 * personal page. See doc/features/employee-self-signup/spec.md.
 */
class SignupController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/AccessCard', ['activeTab' => 'personal-link']);
    }

    public function store(Request $request, SelfSignupService $signups)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $signups->register($data['first_name'], $data['last_name'], $data['email']);

        return redirect()->route('signup.show')->with('success', __('signup.confirmation'));
    }
}
