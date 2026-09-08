<?php

use App\Http\Middleware\AutoLoginSeedUser;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AutoLoginSeedUser::class,
        ]);

        // Route-level `auth` middleware is priority-sorted ahead of any
        // custom global middleware by default, which would run it before
        // AutoLoginSeedUser gets a chance to log the seed user in.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: AutoLoginSeedUser::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
