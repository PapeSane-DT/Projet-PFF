<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Limiter les tentatives de connexion
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->input('telephone') . '|' . $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Trop de tentatives de connexion. Réessayez dans 1 minute.'
                    ], 429);
                });
        });

        // Limiter les requêtes API globales
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}