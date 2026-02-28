<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('webchat-session', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('webchat-message', function (Request $request) {
            $token = $request->header('X-Webchat-Token');
            $key = $token ? substr(md5($token), 0, 16) : $request->ip();
            return Limit::perMinute(20)->by('webchat-msg:' . $key);
        });

        RateLimiter::for('webchat-poll', function (Request $request) {
            $token = $request->header('X-Webchat-Token');
            $key = $token ? substr(md5($token), 0, 16) : $request->ip();
            return Limit::perMinute(30)->by('webchat-poll:' . $key);
        });
    }
}
