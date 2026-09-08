<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginCodeService;
use Illuminate\Http\Request;

class LoginCodeController extends Controller
{
    public function __construct(private LoginCodeService $codes) {}

    public function request(Request $request)
    {
        $email = $request->validate(['email' => ['required', 'email']])['email'];

        $this->codes->request($email);

        return back();
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);

        if ($this->codes->verify($data['email'], $data['code'])) {
            return redirect()->intended('/');
        }

        return back()->withErrors(['code' => __('auth.code_invalid')]);
    }
}
