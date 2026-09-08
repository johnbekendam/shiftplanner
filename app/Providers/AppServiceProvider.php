<?php

namespace App\Providers;

use App\Services\Auth\AuthServiceContract;
use App\Services\Auth\LocalAuthService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuthServiceContract::class, LocalAuthService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Its own bucket so code attempts do not consume the password
        // login throttle. Per-email limiting lives in LoginCodeService.
        RateLimiter::for('login-code', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
