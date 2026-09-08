<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_user_from_config(): void
    {
        config(['auth.seed_user' => [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'super-secret',
        ]]);

        (new DatabaseSeeder)->run();

        $user = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame('Admin', $user->name);
        $this->assertTrue(Hash::check('super-secret', $user->password));
    }

    public function test_reseeding_updates_existing_user(): void
    {
        config(['auth.seed_user' => [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'first-password',
        ]]);
        (new DatabaseSeeder)->run();

        config(['auth.seed_user.password' => 'second-password']);
        (new DatabaseSeeder)->run();

        $this->assertSame(1, User::count());
        $user = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('second-password', $user->password));
    }

    public function test_skips_seeding_when_email_not_configured(): void
    {
        config(['auth.seed_user' => ['name' => 'Admin', 'email' => null, 'password' => null]]);

        (new DatabaseSeeder)->run();

        $this->assertSame(0, User::count());
    }
}
