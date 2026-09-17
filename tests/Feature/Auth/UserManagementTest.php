<?php

namespace Tests\Feature\Auth;

use App\Enums\MessageType;
use App\Mail\ComposedMessage;
use App\Models\BusinessLine;
use App\Models\Message;
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

    public function test_a_manager_can_view_but_not_write_to_users(): void
    {
        $manager = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($manager)->get('/users')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Users/Index'));
        $this->actingAs($manager)->get("/users/{$target->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Users/Form'));

        $this->actingAs($manager)->get('/users/create')->assertForbidden();
        $this->actingAs($manager)
            ->post('/users', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'manager'])
            ->assertForbidden();
        $this->actingAs($manager)->put("/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role,
            'is_active' => $target->is_active,
        ])->assertForbidden();
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

    public function test_user_list_includes_business_line_abbreviations(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Zoe Admin']);
        $line = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        User::factory()->create(['name' => 'Amy Manager', 'business_line_id' => $line->id]);
        User::factory()->create(['name' => 'Ben Manager', 'business_line_id' => null]);

        $this->actingAs($admin)->get('/users')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->where('users.0.business_line', 'PMP')
                ->where('users.1.business_line', null)
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
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/users', ['name' => 'Mel', 'email' => 'mel@example.com', 'role' => 'manager']);

        $user = User::whereEmail('mel@example.com')->sole();
        Mail::assertSent(ComposedMessage::class);
        $this->assertSame(1, $user->loginLinks()->live()->count());

        $message = Message::sole();
        $this->assertSame(MessageType::UserInvite, $message->type);
        $this->assertSame('sent', $message->status);
        $this->assertSame($admin->id, $message->user_id);
    }

    public function test_the_invite_email_signs_off_with_the_admins_first_name(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $admin->update(['name' => 'Alice Admin']);

        $this->actingAs($admin)
            ->post('/users', ['name' => 'Mel', 'email' => 'mel@example.com', 'role' => 'manager']);

        $this->assertStringContainsString("Regards,\nAlice", Message::sole()->body);
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

    public function test_resend_invite_still_works_once_the_user_has_a_password(): void
    {
        Mail::fake();
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->post("/users/{$target->id}/resend-invite")
            ->assertRedirect();

        Mail::assertSent(ComposedMessage::class, 1);
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
        Mail::assertSent(ComposedMessage::class, 2);
        $this->assertSame(2, Message::count());
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

    // ── Business line ───────────────────────────────────────────────────

    public function test_admin_creates_a_user_with_a_business_line(): void
    {
        Mail::fake();
        $line = BusinessLine::factory()->create();

        $this->actingAs($this->admin())
            ->post('/users', [
                'name' => 'Mel', 'email' => 'mel@example.com', 'role' => 'manager', 'business_line_id' => $line->id,
            ])
            ->assertRedirect('/users');

        $user = User::whereEmail('mel@example.com')->sole();
        $this->assertSame($line->id, $user->business_line_id);
    }

    public function test_an_unknown_business_line_is_rejected_when_creating_a_user(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post('/users', ['name' => 'Mel', 'email' => 'mel@example.com', 'role' => 'manager', 'business_line_id' => 999])
            ->assertSessionHasErrors('business_line_id');
    }

    public function test_admin_changes_a_users_business_line(): void
    {
        $line = BusinessLine::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($this->admin())->put("/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role,
            'is_active' => $target->is_active,
            'business_line_id' => $line->id,
        ])->assertRedirect('/users');

        $this->assertSame($line->id, $target->fresh()->business_line_id);
    }

    public function test_edit_payload_includes_the_users_business_line_and_the_full_list(): void
    {
        $line = BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        $target = User::factory()->create(['business_line_id' => $line->id]);

        $this->actingAs($this->admin())->get("/users/{$target->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Form')
                ->where('user.business_line_id', $line->id)
                ->has('businessLines', 1)
                ->where('businessLines.0.abbreviation', 'PMP')
            );
    }

    public function test_changing_a_users_business_line_clears_them_as_responsible_on_their_old_line(): void
    {
        $oldLine = BusinessLine::factory()->create();
        $newLine = BusinessLine::factory()->create();
        $target = User::factory()->create(['business_line_id' => $oldLine->id]);
        $oldLine->update(['responsible_user_id' => $target->id]);

        $this->actingAs($this->admin())->put("/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role,
            'is_active' => $target->is_active,
            'business_line_id' => $newLine->id,
        ])->assertRedirect('/users');

        $this->assertNull($oldLine->fresh()->responsible_user_id);
    }

    public function test_clearing_a_users_business_line_clears_them_as_responsible(): void
    {
        $line = BusinessLine::factory()->create();
        $target = User::factory()->create(['business_line_id' => $line->id]);
        $line->update(['responsible_user_id' => $target->id]);

        $this->actingAs($this->admin())->put("/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role,
            'is_active' => $target->is_active,
            'business_line_id' => null,
        ])->assertRedirect('/users');

        $this->assertNull($line->fresh()->responsible_user_id);
    }

    public function test_keeping_the_same_business_line_does_not_disturb_responsibility(): void
    {
        $line = BusinessLine::factory()->create();
        $target = User::factory()->create(['business_line_id' => $line->id]);
        $line->update(['responsible_user_id' => $target->id]);

        $this->actingAs($this->admin())->put("/users/{$target->id}", [
            'name' => 'Renamed',
            'email' => $target->email,
            'role' => $target->role,
            'is_active' => $target->is_active,
            'business_line_id' => $line->id,
        ])->assertRedirect('/users');

        $this->assertSame($target->id, $line->fresh()->responsible_user_id);
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
