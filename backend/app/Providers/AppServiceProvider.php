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
        RateLimiter::for('public-verify', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip() ?: 'unknown');
        });

        RateLimiter::for('login', function (Request $request) {
            $max = (int) config('auth.login_rate_limit');

            return Limit::perMinute($max > 0 ? $max : 30)->by($request->ip() ?: 'unknown');
        });

        RateLimiter::for('api', function (Request $request) {
            $max = (int) config('auth.api_rate_limit');

            return Limit::perMinute($max > 0 ? $max : 60)->by(
                $request->user()?->getAuthIdentifier() ?: ($request->ip() ?: 'unknown')
            );
        });
    }
}
