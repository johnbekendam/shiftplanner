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

    public function show(string $token)
    {
        $link = $this->links->resolve($token);

        if ($link) {
            return Inertia::render(
                $link->purpose === LoginLink::PURPOSE_INVITE ? 'Auth/SetPassword' : 'Auth/SignInLink',
                ['token' => $token],
            );
        }

        return Inertia::render('Auth/LinkExpired', ['purpose' => $this->purposeOf($token)]);
    }

    public function confirm(Request $request, string $token)
    {
        $link = $this->links->resolve($token);

        abort_unless($link, 404);

        if ($link->purpose === LoginLink::PURPOSE_INVITE) {
            $data = $request->validate([
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $this->links->consumeInvite($link, $data['password']);
        } else {
            $this->links->consumeLogin($link);
        }

        return redirect()->intended('/');
    }

    /** Sign in on the invite link itself, without setting a password. */
    public function skip(string $token)
    {
        $link = $this->links->resolve($token);

        abort_unless($link && $link->purpose === LoginLink::PURPOSE_INVITE, 404);

        $this->links->consumeLogin($link);

        return redirect()->intended('/');
    }

    /** The purpose of a token that did not resolve live, for the expired page's copy. Null when the token is unknown. */
    private function purposeOf(string $token): ?string
    {
        return LoginLink::where('token_hash', hash('sha256', $token))->value('purpose');
    }
}
