<?php

namespace App\Providers;

use App\Mail\Transport\GraphTransport;
use App\Services\Auth\AuthServiceContract;
use App\Services\Auth\LocalAuthService;
use App\Services\Graph\GraphClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

        $this->app->singleton(GraphClient::class, fn () => new GraphClient(
            (string) config('services.graph.tenant_id'),
            (string) config('services.graph.client_id'),
            (string) config('services.graph.client_secret'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Its own bucket so code attempts do not consume the password
        // login throttle. Per-email limiting lives in LoginCodeService.
        RateLimiter::for('login-code', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // The `graph` mailer — inert until MAIL_MAILER=graph and GRAPH_* are set.
        Mail::extend('graph', fn (array $config) => new GraphTransport(
            $this->app->make(GraphClient::class),
            (string) config('services.graph.mail_from'),
            (bool) ($config['save_to_sent_items'] ?? true),
        ));
    }
}
