<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoLoginSeedUserTest extends TestCase
{
    use RefreshDatabase;

    private function seedUser(): User
    {
        return User::factory()->create(['email' => 'admin@example.com']);
    }

    public function test_does_not_auto_login_when_flag_disabled(): void
    {
        $this->seedUser();
        config(['auth.auto_login' => false, 'auth.seed_user.email' => 'admin@example.com', 'app.debug' => true]);
        $this->app->instance('env', 'local');

        $this->get('/mailbox')->assertRedirect('/login');
    }

    public function test_does_not_auto_login_outside_local_environment(): void
    {
        $this->seedUser();
        config(['auth.auto_login' => true, 'auth.seed_user.email' => 'admin@example.com', 'app.debug' => true]);
        $this->app->instance('env', 'testing');

        $this->get('/mailbox')->assertRedirect('/login');
    }

    public function test_does_not_auto_login_when_debug_disabled(): void
    {
        $this->seedUser();
        config(['auth.auto_login' => true, 'auth.seed_user.email' => 'admin@example.com', 'app.debug' => false]);
        $this->app->instance('env', 'local');

        $this->get('/mailbox')->assertRedirect('/login');
    }

    public function test_auto_logs_in_seed_user_when_all_conditions_met(): void
    {
        $user = $this->seedUser();
        config(['auth.auto_login' => true, 'auth.seed_user.email' => 'admin@example.com', 'app.debug' => true]);
        $this->app->instance('env', 'local');

        $this->get('/mailbox')->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
