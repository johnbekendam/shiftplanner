<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthServiceContract;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function __construct(private AuthServiceContract $auth) {}

    public function showForm(Request $request)
    {
        if ($request->user()) {
            return redirect('/');
        }

        return Inertia::render('Auth/AccessCard');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if ($this->auth->attempt($credentials['email'], $credentials['password'])) {
            return redirect()->intended('/');
        }

        return back()->withErrors(['email' => __('auth.failed')]);
    }

    public function destroy(Request $request)
    {
        $this->auth->logout();

        return redirect('/login');
    }
}
