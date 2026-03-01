<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::domain('admin.' . config('app.base_domain'))
                ->middleware('web')
                ->group(function () {
                    Route::get('/', function () {
                        return redirect('/panel');
                    });
                });

            Route::domain('admin.' . config('app.base_domain'))
                ->middleware(['web', 'auth', 'saas_admin'])
                ->prefix('panel')
                ->group(base_path('routes/saas_admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\EnsureProductionSafety::class);
        $middleware->alias([
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'subscription' => \App\Http\Middleware\EnsureActiveSubscription::class,
            'feature' => \App\Http\Middleware\CheckFeature::class,
            'usage.limit' => \App\Http\Middleware\CheckUsageLimit::class,
            'saas_admin' => \App\Http\Middleware\ResolveSaaSAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
