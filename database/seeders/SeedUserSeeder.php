<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SeedUserSeeder extends Seeder
{
    public function run(): void
    {
        $seedUser = config('auth.seed_user');

        if ($seedUser['email'] && $seedUser['password']) {
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
