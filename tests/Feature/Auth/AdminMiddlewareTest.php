<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $adminRoutes = ['/settings', '/theme-builder', '/mailbox'];

    public function test_a_manager_is_forbidden_from_admin_routes(): void
    {
        $manager = User::factory()->create();

        foreach ($this->adminRoutes as $path) {
            $this->actingAs($manager)->get($path)->assertForbidden();
        }
    }

    public function test_an_admin_reaches_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ($this->adminRoutes as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_a_guest_is_redirected_to_login_from_admin_routes(): void
    {
        foreach ($this->adminRoutes as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_a_manager_can_still_reach_the_employee_list(): void
    {
        $this->actingAs(User::factory()->create())->get('/employees')->assertOk();
    }
}
