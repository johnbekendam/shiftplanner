<?php

namespace App\Services\Auth;

use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Password-less sign-in with a one-time code sent by email. Local only:
 * it is not part of AuthServiceContract, since an SSO provider would not
 * use it.
 */
class LoginCodeService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const REQUEST_MAX = 5;

    public const REQUEST_WINDOW_SECONDS = 900;

    public function __construct(private Request $request) {}

    /**
     * Issue a fresh code for the email and send it. Silent for an unknown
     * or inactive email, and once the per-email request limit is hit.
     */
    public function request(string $email): void
    {
        $key = 'login-code:'.Str::lower($email);

        if (RateLimiter::tooManyAttempts($key, self::REQUEST_MAX)) {
            return;
        }

        RateLimiter::hit($key, self::REQUEST_WINDOW_SECONDS);

        $user = $this->activeUser($email);

        if (! $user) {
            return;
        }

        $user->loginCodes()->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->loginCodes()->create([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        Mail::to($user->email)->send(new LoginCodeMail($code));
    }

    /** Check a code and, on success, sign the user in. */
    public function verify(string $email, string $code): bool
    {
        $user = $this->activeUser($email);

        if (! $user) {
            return false;
        }

        $row = $user->loginCodes()->live()->latest()->first();

        if (! $row) {
            return false;
        }

        $row->increment('attempts');

        if (! Hash::check($code, $row->code_hash)) {
            if ($row->attempts >= self::MAX_ATTEMPTS) {
                $row->update(['consumed_at' => now()]);
            }

            return false;
        }

        $row->update(['consumed_at' => now()]);

        Auth::login($user, remember: true);
        $this->request->session()->regenerate();

        return true;
    }

    private function activeUser(string $email): ?User
    {
        return User::where('email', $email)->where('is_active', true)->first();
    }
}
