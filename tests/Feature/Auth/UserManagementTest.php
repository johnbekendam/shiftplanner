<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginLinkMail;
use App\Models\LoginLink;
use App\Models\User;
use App\Services\Auth\LoginLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_a_guest_is_redirected_from_users(): void
    {
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_a_manager_is_forbidden_from_users(): void
    {
        $this->actingAs(User::factory()->create())->get('/users')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->post('/users', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'manager'])
            ->assertForbidden();
    }

    public function test_an_admin_sees_the_user_list(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/users')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->has('users', 1)
                ->where('users.0.role', User::ROLE_ADMIN)
            );
    }

    // ── Create ──────────────────────────────────────────────────────────

    public function test_admin_creates_a_password_less_user(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post('/users', ['name' => 'Mel', 'email' => 'mel@example.com', 'role' => 'manager'])
            ->assertRedirect('/users');

        $user = User::whereEmail('mel@example.com')->sole();
        $this->assertNull($user->password);
        $this->assertSame(User::ROLE_MANAGER, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_creating_a_user_sends_an_invite_link(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post('/users', ['name' => 'Mel', 'email' => 'mel@example.com', 'role' => 'manager']);

        $user = User::whereEmail('mel@example.com')->sole();
        Mail::assertSent(LoginLinkMail::class, fn (LoginLinkMail $mail) => $mail->purpose === LoginLink::PURPOSE_INVITE);
        $this->assertSame(1, $user->loginLinks()->live()->count());
    }

    // ── Resend invite ───────────────────────────────────────────────────

    public function test_a_manager_cannot_resend_an_invite(): void
    {
        Mail::fake();
        $target = User::factory()->passwordless()->create();

        $this->actingAs(User::factory()->create())
            ->post("/users/{$target->id}/resend-invite")
            ->assertForbidden();
    }

    public function test_resend_invite_is_refused_once_the_user_has_a_password(): void
    {
        Mail::fake();
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->post("/users/{$target->id}/resend-invite")
            ->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_admin_resends_an_invite_and_voids_the_previous_link(): void
    {
        Mail::fake();
        $target = User::factory()->passwordless()->create();
        app(LoginLinkService::class)->sendInvite($target);
        $first = $target->loginLinks()->sole();

        $this->actingAs($this->admin())
            ->post("/users/{$target->id}/resend-invite")
            ->assertRedirect();

        $this->assertNotNull($first->fresh()->consumed_at);
        $this->assertSame(1, $target->loginLinks()->live()->count());
        Mail::assertSent(LoginLinkMail::class, 2);
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        $this->admin();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin())
            ->post('/users', ['name' => 'Dup', 'email' => 'taken@example.com', 'role' => 'manager'])
            ->assertSessionHasErrors('email');
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/users', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'wizard'])
            ->assertSessionHasErrors('role');
    }

    // ── Edit ────────────────────────────────────────────────────────────

    public function test_admin_changes_role_and_active_state(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Old', 'role' => User::ROLE_MANAGER]);

        $this->actingAs($admin)->put("/users/{$target->id}", [
            'name' => 'New',
            'email' => $target->email,
            'role' => User::ROLE_ADMIN,
            'is_active' => false,
        ])->assertRedirect('/users');

        $target->refresh();
        $this->assertSame('New', $target->name);
        $this->assertSame(User::ROLE_ADMIN, $target->role);
        $this->assertFalse($target->is_active);
    }

    // ── Last-admin guard ────────────────────────────────────────────────

    public function test_the_last_active_admin_cannot_be_deactivated(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => User::ROLE_ADMIN,
            'is_active' => false,
        ])->assertSessionHasErrors();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_the_last_active_admin_cannot_be_demoted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ])->assertSessionHasErrors();

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    public function test_an_admin_can_be_demoted_when_another_active_admin_exists(): void
    {
        $a = $this->admin();
        $b = $this->admin();

        $this->actingAs($a)->put("/users/{$b->id}", [
            'name' => $b->name,
            'email' => $b->email,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ])->assertRedirect('/users');

        $this->assertSame(User::ROLE_MANAGER, $b->fresh()->role);
    }

    public function test_a_deactivated_admin_can_be_reactivated(): void
    {
        $active = $this->admin();
        $dormant = User::factory()->admin()->inactive()->create();

        $this->actingAs($active)->put("/users/{$dormant->id}", [
            'name' => $dormant->name,
            'email' => $dormant->email,
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ])->assertRedirect('/users');

        $this->assertTrue($dormant->fresh()->is_active);
    }
}
