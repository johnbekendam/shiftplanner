<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocalAuthService implements AuthServiceContract
{
    public function __construct(private Request $request) {}

    public function attempt(string $email, string $password): bool
    {
        // is_active baked into the credentials array: a deactivated
        // user's password simply stops matching, no separate check needed.
        $ok = Auth::attempt([
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ], remember: true);

        if ($ok) {
            $this->request->session()->regenerate();
        }

        return $ok;
    }

    public function logout(): void
    {
        Auth::logout();
        $this->request->session()->invalidate();
        $this->request->session()->regenerateToken();
    }
}
