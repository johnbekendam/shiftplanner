<?php

namespace App\Http\Controllers;

use App\Services\SelfSignupService;
use Illuminate\Http\Request;

/**
 * Resends an existing employee's personal-page link, the "Get my link"
 * tab on the login card. Never creates an employee — that is /signup's
 * job. See doc/features/auth-tabbed-card/spec.md.
 */
class PersonalLinkController extends Controller
{
    public function request(Request $request, SelfSignupService $signups)
    {
        $email = $request->validate(['email' => ['required', 'email', 'max:255']])['email'];

        $signups->resend($email);

        return back();
    }
}
