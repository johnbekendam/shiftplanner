<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccountFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_user_gets_the_admin_role(): void
    {
        config(['auth.seed_user' => [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'super-secret',
        ]]);

        (new DatabaseSeeder)->run();

        $this->assertSame(User::ROLE_ADMIN, User::whereEmail('admin@example.com')->firstOrFail()->role);
    }

    public function test_user_factory_defaults_to_manager_with_an_admin_state(): void
    {
        $this->assertSame(User::ROLE_MANAGER, User::factory()->create()->role);
        $this->assertSame(User::ROLE_ADMIN, User::factory()->admin()->create()->role);

        $this->assertTrue(User::factory()->admin()->make()->isAdmin());
        $this->assertFalse(User::factory()->make()->isAdmin());
    }

    public function test_user_factory_passwordless_state_has_no_password(): void
    {
        $this->assertNull(User::factory()->passwordless()->create()->password);
    }

    public function test_a_user_links_to_at_most_one_employee_and_the_link_clears_on_delete(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['employee_id' => $employee->id]);

        $this->assertTrue($user->employee->is($employee));
        $this->assertTrue($employee->user->is($user));

        $employee->delete();

        $this->assertNull($user->fresh()->employee_id);
    }

    public function test_shared_auth_user_carries_role_and_employee_id(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->admin()->create(['employee_id' => $employee->id]);

        $this->actingAs($user)->get('/employees')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.role', User::ROLE_ADMIN)
                ->where('auth.user.employee_id', $employee->id)
            );
    }
}
