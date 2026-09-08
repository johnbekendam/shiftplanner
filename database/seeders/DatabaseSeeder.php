<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(EmployeeSeeder::class);

        $seedUser = config('auth.seed_user');

        if ($seedUser['email'] && $seedUser['password']) {
            // The 'password' => 'hashed' cast on User hashes this on save.
            User::updateOrCreate(
                ['email' => $seedUser['email']],
                [
                    'name' => $seedUser['name'],
                    'password' => $seedUser['password'],
                    'role' => User::ROLE_ADMIN,
                ],
            );
        }
    }
}
