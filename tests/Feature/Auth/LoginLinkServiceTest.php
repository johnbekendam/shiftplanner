<?php

namespace Tests\Feature\Auth;

use App\Enums\MessageType;
use App\Mail\ComposedMessage;
use App\Models\LoginLink;
use App\Models\Message;
use App\Models\User;
use App\Services\Auth\LoginLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    private function sentUrl(): string
    {
        $mail = Mail::sent(ComposedMessage::class)->last();
        preg_match('#href="([^"]+)"#', $mail->bodyHtml, $matches);

        return $matches[1];
    }

    private function tokenFromUrl(string $url): string
    {
        return basename($url);
    }

    /** Bind a request with a session, so a service that regenerates it does not blow up. */
    private function bindSessionBackedRequest(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);
    }

    public function test_send_invite_creates_a_live_seven_day_row_and_emails_a_link(): void
    {
        Mail::fake();
        $user = User::factory()->passwordless()->create();

        app(LoginLinkService::class)->sendInvite($user);

        Mail::assertSent(ComposedMessage::class);
        $link = $user->loginLinks()->sole();
        $this->assertSame(LoginLink::PURPOSE_INVITE, $link->purpose);
        $this->assertTrue($link->expires_at->isBetween(now()->addDays(6), now()->addDays(8)));
        $this->assertNotSame($this->tokenFromUrl($this->sentUrl()), $link->token_hash);
    }

    public function test_send_invite_records_a_sent_message_in_the_mailbox(): void
    {
        Mail::fake();
        $user = User::factory()->passwordless()->create(['name' => 'Mel Manager']);

        app(LoginLinkService::class)->sendInvite($user);

        $message = Message::sole();
        $this->assertSame(MessageType::UserInvite, $message->type);
        $this->assertSame('sent', $message->status);
        $this->assertNotNull($message->sent_at);
        $this->assertSame($user->email, $message->recipient_email);
        $this->assertStringContainsString('/login/link/', $message->body_html);
    }

    public function test_a_second_invite_voids_the_first(): void
    {
        Mail::fake();
        $user = User::factory()->passwordless()->create();
        $service = app(LoginLinkService::class);

        $service->sendInvite($user);
        $first = $user->loginLinks()->sole();

        $service->sendInvite($user);

        $this->assertNotNull($first->fresh()->consumed_at);
        $this->assertSame(1, $user->loginLinks()->live()->count());
    }

    public function test_requesting_a_login_link_for_an_unknown_email_sends_nothing(): void
    {
        Mail::fake();

        app(LoginLinkService::class)->requestLogin('nobody@example.com');

        Mail::assertNothingSent();
        $this->assertSame(0, LoginLink::count());
        $this->assertSame(0, Message::count());
    }

    public function test_requesting_a_login_link_for_an_inactive_account_sends_nothing(): void
    {
        Mail::fake();
        User::factory()->inactive()->create(['email' => 'gone@example.com']);

        app(LoginLinkService::class)->requestLogin('gone@example.com');

        Mail::assertNothingSent();
        $this->assertSame(0, LoginLink::count());
    }

    public function test_requesting_a_login_link_for_an_active_account_emails_a_login_purpose_link(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'user@example.com']);

        app(LoginLinkService::class)->requestLogin('user@example.com');

        Mail::assertSent(ComposedMessage::class);
        $this->assertSame(1, LoginLink::count());
        $message = Message::sole();
        $this->assertSame(MessageType::UserLoginLink, $message->type);
        $this->assertNull($message->user_id);
    }

    public function test_the_sixth_login_link_request_within_the_window_is_dropped(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'user@example.com']);
        $service = app(LoginLinkService::class);

        for ($i = 0; $i < 6; $i++) {
            $service->requestLogin('user@example.com');
        }

        Mail::assertSent(ComposedMessage::class, 5);
        $this->assertSame(5, Message::count());
    }

    public function test_resolve_finds_a_live_link_and_misses_an_expired_or_consumed_one(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $service = app(LoginLinkService::class);

        $service->requestLogin($user->email);
        $token = $this->tokenFromUrl($this->sentUrl());

        $this->assertNotNull($service->resolve($token));

        $service->resolve($token)->update(['consumed_at' => now()]);
        $this->assertNull($service->resolve($token));

        $service->requestLogin($user->email);
        $token = $this->tokenFromUrl($this->sentUrl());
        $service->resolve($token)->update(['expires_at' => now()->subMinute()]);
        $this->assertNull($service->resolve($token));
    }

    public function test_resolve_misses_a_link_for_an_inactive_user(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $service = app(LoginLinkService::class);
        $service->requestLogin($user->email);
        $token = $this->tokenFromUrl($this->sentUrl());

        $user->update(['is_active' => false]);

        $this->assertNull($service->resolve($token));
    }

    public function test_consume_login_signs_in_and_marks_consumed(): void
    {
        Mail::fake();
        $this->startSession();
        $user = User::factory()->create();
        $service = app(LoginLinkService::class);
        $service->requestLogin($user->email);
        $link = $service->resolve($this->tokenFromUrl($this->sentUrl()));

        $this->bindSessionBackedRequest();
        app(LoginLinkService::class)->consumeLogin($link);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($link->fresh()->consumed_at);
    }

    public function test_consume_invite_sets_the_password_signs_in_and_marks_consumed(): void
    {
        Mail::fake();
        $this->startSession();
        $user = User::factory()->passwordless()->create();
        $service = app(LoginLinkService::class);
        $service->sendInvite($user);
        $link = $service->resolve($this->tokenFromUrl($this->sentUrl()));

        $this->bindSessionBackedRequest();
        app(LoginLinkService::class)->consumeInvite($link, 'a-new-password');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($link->fresh()->consumed_at);
        $this->assertTrue(Hash::check('a-new-password', $user->fresh()->password));
    }
}
