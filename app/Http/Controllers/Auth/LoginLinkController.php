<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginLink;
use App\Services\Auth\LoginLinkService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoginLinkController extends Controller
{
    public function __construct(private LoginLinkService $links) {}

    public function request(Request $request)
    {
        $email = $request->validate(['email' => ['required', 'email']])['email'];

        $this->links->requestLogin($email);

        return back();
    }

    /** Every live link — invite or login-purpose — lands on the same set-password page. */
    public function show(string $token)
    {
        $link = $this->links->resolve($token);

        if ($link) {
            return Inertia::render('Auth/SetPassword', ['token' => $token]);
        }

        return Inertia::render('Auth/LinkExpired', ['purpose' => $this->purposeOf($token)]);
    }

    public function confirm(Request $request, string $token)
    {
        $link = $this->links->resolve($token);

        abort_unless($link, 404);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->links->consumeWithPassword($link, $data['password']);

        return redirect()->intended('/');
    }

    /** Sign in on the link itself, without setting a password. */
    public function skip(string $token)
    {
        $link = $this->links->resolve($token);

        abort_unless($link, 404);

        $this->links->consumeWithoutPassword($link);

        return redirect()->intended('/');
    }

    /** The purpose of a token that did not resolve live, for the expired page's copy. Null when the token is unknown. */
    private function purposeOf(string $token): ?string
    {
        return LoginLink::where('token_hash', hash('sha256', $token))->value('purpose');
    }
}
