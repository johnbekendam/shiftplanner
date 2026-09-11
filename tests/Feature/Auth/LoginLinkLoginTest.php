<?php

namespace Tests\Feature\Auth;

use App\Mail\ComposedMessage;
use App\Models\LoginLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginLinkLoginTest extends TestCase
{
    use RefreshDatabase;

    private function loginToken(User $user): string
    {
        Mail::fake();
        $this->post('/login/link', ['email' => $user->email]);

        return basename($this->sentUrl());
    }

    private function sentUrl(): string
    {
        $mail = Mail::sent(ComposedMessage::class)->last();
        preg_match('#href="([^"]+)"#', $mail->bodyHtml, $matches);

        return $matches[1];
    }

    public function test_requesting_a_link_for_an_unknown_email_sends_nothing_but_still_responds_ok(): void
    {
        Mail::fake();

        $this->post('/login/link', ['email' => 'nobody@example.com'])->assertRedirect();

        Mail::assertNothingSent();
        $this->assertSame(0, LoginLink::count());
    }

    public function test_requesting_a_link_for_an_inactive_account_sends_nothing(): void
    {
        Mail::fake();
        User::factory()->inactive()->create(['email' => 'gone@example.com']);

        $this->post('/login/link', ['email' => 'gone@example.com'])->assertRedirect();

        Mail::assertNothingSent();
    }

    public function test_a_live_login_link_renders_the_set_password_page(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->get("/login/link/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/SetPassword')->where('token', $token));
    }

    public function test_skipping_a_login_link_signs_the_user_in_without_touching_the_password(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);
        $originalPassword = $user->password;

        $this->post("/login/link/{$token}/skip")->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->loginLinks()->sole()->consumed_at);
        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_submitting_a_password_on_a_login_link_sets_it_and_signs_in(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->post("/login/link/{$token}", [
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->loginLinks()->sole()->consumed_at);
    }

    public function test_an_inactive_users_link_is_refused_at_request_and_at_confirm(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $user->update(['is_active' => false]);

        $this->get("/login/link/{$token}")
            ->assertInertia(fn ($page) => $page->component('Auth/LinkExpired'));
        $this->post("/login/link/{$token}/skip")->assertNotFound();
        $this->assertGuest();
    }

    public function test_a_new_request_voids_the_previous_link(): void
    {
        $user = User::factory()->create();
        $first = $this->loginToken($user);
        $second = $this->loginToken($user);

        $this->post("/login/link/{$first}/skip")->assertNotFound();
        $this->assertGuest();

        $this->post("/login/link/{$second}/skip")->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_the_sixth_request_within_the_window_is_dropped(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'user@example.com']);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login/link', ['email' => 'user@example.com'])->assertRedirect();
        }

        Mail::assertSent(ComposedMessage::class, 5);
    }
}
