<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('contact', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('rss-import', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('almanar-urgent-import', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('api-breaking', function (Request $request): Limit {
            return Limit::perMinute(120)->by((string) $request->ip());
        });

        RateLimiter::for('membership', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->ip());
        });
    }
}
