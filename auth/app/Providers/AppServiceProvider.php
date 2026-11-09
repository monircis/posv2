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
        // Each kind of call has its own count per computer (IP), so renewing tokens never uses up sign-in attempts.
        RateLimiter::for('sign-in', function (Request $request) {
            return Limit::perMinute(10)->by('sign-in|'.$request->ip());
        });
        RateLimiter::for('token', function (Request $request) {
            return Limit::perMinute(60)->by('token|'.$request->ip());
        });
    }
}
