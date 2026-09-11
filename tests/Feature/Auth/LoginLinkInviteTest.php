<?php

namespace Tests\Feature\Auth;

use App\Mail\ComposedMessage;
use App\Models\LoginLink;
use App\Models\User;
use App\Services\Auth\LoginLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginLinkInviteTest extends TestCase
{
    use RefreshDatabase;

    private function inviteToken(User $user): string
    {
        Mail::fake();
        app(LoginLinkService::class)->sendInvite($user);

        return basename($this->sentUrl());
    }

    private function sentUrl(): string
    {
        $mail = Mail::sent(ComposedMessage::class)->last();
        preg_match('#href="([^"]+)"#', $mail->bodyHtml, $matches);

        return $matches[1];
    }

    public function test_a_live_invite_link_renders_the_set_password_page(): void
    {
        $user = User::factory()->passwordless()->create();
        $token = $this->inviteToken($user);

        $this->get("/login/link/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/SetPassword')->where('token', $token));
    }

    public function test_an_expired_invite_link_renders_the_expired_page(): void
    {
        $user = User::factory()->passwordless()->create();
        $token = $this->inviteToken($user);
        $user->loginLinks()->sole()->update(['expires_at' => now()->subMinute()]);

        $this->get("/login/link/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/LinkExpired')->where('purpose', LoginLink::PURPOSE_INVITE));
    }

    public function test_a_consumed_invite_link_renders_the_expired_page(): void
    {
        $user = User::factory()->passwordless()->create();
        $token = $this->inviteToken($user);
        $user->loginLinks()->sole()->update(['consumed_at' => now()]);

        $this->get("/login/link/{$token}")
            ->assertInertia(fn ($page) => $page->component('Auth/LinkExpired')->where('purpose', LoginLink::PURPOSE_INVITE));
    }

    public function test_an_unknown_token_renders_the_expired_page_with_no_purpose(): void
    {
        $this->get('/login/link/'.Str::random(40))
            ->assertInertia(fn ($page) => $page->component('Auth/LinkExpired')->where('purpose', null));
    }

    public function test_submitting_a_valid_password_signs_in_and_consumes_the_link(): void
    {
        $user = User::factory()->passwordless()->create();
        $token = $this->inviteToken($user);

        $this->post("/login/link/{$token}", [
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('a-new-password', $user->fresh()->password));
        $this->assertNotNull($user->loginLinks()->sole()->consumed_at);
    }

    public function test_a_mismatched_confirmation_fails_validation(): void
    {
        $user = User::factory()->passwordless()->create();
        $token = $this->inviteToken($user);

        $this->post("/login/link/{$token}", [
            'password' => 'a-new-password',
            'password_confirmation' => 'does-not-match',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertNull($user->fresh()->password);
    }

    public function test_confirming_an_expired_invite_link_is_a_404(): void
    {
        $user = User::factory()->passwordless()->create();
        $token = $this->inviteToken($user);
        $user->loginLinks()->sole()->update(['expires_at' => now()->subMinute()]);

        $this->post("/login/link/{$token}", [
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertNotFound();
    }

    // ── Skip password ───────────────────────────────────────────────────

    public function test_skip_signs_in_and_consumes_the_link_without_a_password(): void
    {
        $user = User::factory()->passwordless()->create();
        $token = $this->inviteToken($user);

        $this->post("/login/link/{$token}/skip")->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->password);
        $this->assertNotNull($user->loginLinks()->sole()->consumed_at);
    }

    public function test_skip_on_an_expired_invite_link_is_a_404(): void
    {
        $user = User::factory()->passwordless()->create();
        $token = $this->inviteToken($user);
        $user->loginLinks()->sole()->update(['expires_at' => now()->subMinute()]);

        $this->post("/login/link/{$token}/skip")->assertNotFound();
        $this->assertGuest();
    }

    public function test_skip_also_signs_in_a_login_purpose_link(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        app(LoginLinkService::class)->requestLogin($user->email);
        $token = basename($this->sentUrl());

        $this->post("/login/link/{$token}/skip")->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->loginLinks()->sole()->consumed_at);
    }
}
