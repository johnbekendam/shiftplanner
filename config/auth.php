<?php

use App\Models\User;

return [

    'defaults' => [
        'guard' => 'web',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    // Dev convenience: automatically log in the seed user below when
    // enabled. Gated in the middleware by environment + debug checks in
    // addition to this flag — see App\Http\Middleware\AutoLoginSeedUser.
    'auto_login' => env('AUTH_AUTO_LOGIN', false),

    // Upserted by DatabaseSeeder on every `db:seed` run, and looked up by
    // email for auto-login. Left null in production unless explicitly set.
    'seed_user' => [
        'name' => env('SEED_USER_NAME', 'Admin'),
        'email' => env('SEED_USER_EMAIL'),
        'password' => env('SEED_USER_PASSWORD'),
    ],

];
