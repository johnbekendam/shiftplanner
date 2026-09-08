<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use App\Models\User;
use App\Services\Auth\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginCodeTest extends TestCase
{
    use RefreshDatabase;

    private function sentCode(): string
    {
        return Mail::sent(LoginCodeMail::class)->last()->code;
    }

    public function test_requesting_a_code_for_an_unknown_email_sends_nothing_but_still_responds_ok(): void
    {
        Mail::fake();

        $this->post('/login/code', ['email' => 'nobody@example.com'])->assertRedirect();

        Mail::assertNothingSent();
        $this->assertSame(0, LoginCode::count());
    }

    public function test_requesting_a_code_for_an_inactive_account_sends_nothing(): void
    {
        Mail::fake();
        User::factory()->inactive()->create(['email' => 'gone@example.com']);

        $this->post('/login/code', ['email' => 'gone@example.com'])->assertRedirect();

        Mail::assertNothingSent();
        $this->assertSame(0, LoginCode::count());
    }

    public function test_requesting_a_code_for_an_active_account_stores_a_hash_and_emails_the_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->post('/login/code', ['email' => 'user@example.com'])->assertRedirect();

        Mail::assertSent(LoginCodeMail::class);
        $this->assertSame(1, $user->loginCodes()->count());
        $this->assertNotSame($this->sentCode(), $user->loginCodes()->sole()->code_hash);
    }

    public function test_a_valid_code_signs_the_user_in(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->post('/login/code', ['email' => 'user@example.com']);
        $this->post('/login/code/verify', ['email' => 'user@example.com', 'code' => $this->sentCode()])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->loginCodes()->sole()->consumed_at);
    }

    public function test_a_wrong_code_is_counted_and_the_fifth_wrong_try_burns_it(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);
        $this->post('/login/code', ['email' => 'user@example.com']);
        $good = $this->sentCode();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login/code/verify', ['email' => 'user@example.com', 'code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->assertGuest();
        $this->assertNotNull($user->loginCodes()->sole()->consumed_at);

        $this->post('/login/code/verify', ['email' => 'user@example.com', 'code' => $good])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_an_expired_code_does_not_verify(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);
        app(LoginCodeService::class)->request('user@example.com');
        $code = $this->sentCode();

        $this->travel(11)->minutes();

        $this->post('/login/code/verify', ['email' => 'user@example.com', 'code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_new_request_voids_the_previous_code(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'user@example.com']);

        $this->post('/login/code', ['email' => 'user@example.com']);
        $first = $this->sentCode();
        $this->post('/login/code', ['email' => 'user@example.com']);
        $second = $this->sentCode();

        $this->assertNotSame($first, $second);

        $this->post('/login/code/verify', ['email' => 'user@example.com', 'code' => $first])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/login/code/verify', ['email' => 'user@example.com', 'code' => $second])
            ->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_the_sixth_request_within_the_window_is_dropped(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'user@example.com']);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login/code', ['email' => 'user@example.com'])->assertRedirect();
        }

        Mail::assertSent(LoginCodeMail::class, 5);
    }
}
