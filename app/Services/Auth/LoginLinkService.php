<?php

namespace App\Services\Auth;

use App\Enums\MessageType;
use App\Mail\ComposedMessage;
use App\Models\LoginLink;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\MessageComposer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Password-less sign-in and account setup by emailed link. Local only:
 * it is not part of AuthServiceContract, since an SSO provider would not
 * use it.
 */
class LoginLinkService
{
    public const INVITE_TTL_DAYS = 7;

    public const LOGIN_TTL_MINUTES = 15;

    public const REQUEST_MAX = 5;

    public const REQUEST_WINDOW_SECONDS = 900;

    public function __construct(private Request $request, private MessageComposer $composer) {}

    /** Void any live invite link for the user, issue one, and email it. */
    public function sendInvite(User $user): void
    {
        $this->issue($user, LoginLink::PURPOSE_INVITE, now()->addDays(self::INVITE_TTL_DAYS), $this->request->user());
    }

    /**
     * Issue a fresh login link for the email and send it. Silent for an
     * unknown or inactive email, and once the per-email request limit is
     * hit.
     */
    public function requestLogin(string $email): void
    {
        $key = 'login-link:'.Str::lower($email);

        if (RateLimiter::tooManyAttempts($key, self::REQUEST_MAX)) {
            return;
        }

        RateLimiter::hit($key, self::REQUEST_WINDOW_SECONDS);

        $user = $this->activeUser($email);

        if (! $user) {
            return;
        }

        // No composing admin: the recipient requested this themselves.
        $this->issue($user, LoginLink::PURPOSE_LOGIN, now()->addMinutes(self::LOGIN_TTL_MINUTES), null);
    }

    /**
     * Resolve a live token to its link, or null when it does not match
     * or its user is no longer active.
     */
    public function resolve(string $token): ?LoginLink
    {
        return LoginLink::query()
            ->live()
            ->where('token_hash', hash('sha256', $token))
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->with('user')
            ->first();
    }

    /** Sign the login-purpose link's user in and consume the link. */
    public function consumeLogin(LoginLink $link): void
    {
        $link->update(['consumed_at' => now()]);
        $this->signIn($link->user);
    }

    /** Set the invite-purpose link's user's password, sign in, and consume the link. */
    public function consumeInvite(LoginLink $link, string $password): void
    {
        $link->user->update(['password' => $password]);
        $link->update(['consumed_at' => now()]);
        $this->signIn($link->user);
    }

    private function issue(User $user, string $purpose, \DateTimeInterface $expiresAt, ?User $composedBy): void
    {
        $user->loginLinks()->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $token = Str::random(40);

        $user->loginLinks()->create([
            'token_hash' => hash('sha256', $token),
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
        ]);

        $this->send($user, $purpose, url("/login/link/{$token}"), $composedBy);
    }

    /**
     * Render through the mailbox pipeline (branded HTML, admin-editable
     * template) and send synchronously — matching account-management's
     * no-queued-mail decision. The row lands straight in the mailbox
     * Sent tab, since delivery already happened.
     */
    private function send(User $user, string $purpose, string $url, ?User $composedBy): void
    {
        $type = $purpose === LoginLink::PURPOSE_INVITE ? MessageType::UserInvite : MessageType::UserLoginLink;
        $template = MessageTemplate::forType($type);
        $map = [':name' => $user->name, ':link' => $url];
        $subject = strtr($template->subject, $map);
        $body = strtr($template->body, $map);
        $fragment = $this->composer->render($subject, $body)['body_html'];
        $mailable = new ComposedMessage($subject, $fragment, $composedBy?->email, $composedBy?->name);

        Mail::to($user->email)->send($mailable);

        Message::create([
            'user_id' => $composedBy?->id,
            'type' => $type,
            'recipient_email' => $user->email,
            'recipient_name' => $user->name,
            'subject' => $subject,
            'body' => $body,
            'body_html' => new ComposedMessage($subject, $fragment, logoSrc: ComposedMessage::browserLogoUrl())->render(),
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    private function signIn(User $user): void
    {
        Auth::login($user, remember: true);
        $this->request->session()->regenerate();
    }

    private function activeUser(string $email): ?User
    {
        return User::where('email', $email)->where('is_active', true)->first();
    }
}
