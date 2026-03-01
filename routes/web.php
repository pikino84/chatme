<?php

use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landing Domain (chatme.com.mx)
|--------------------------------------------------------------------------
| Public landing page. No auth, no tenant middleware.
*/

Route::domain(config('app.base_domain'))->group(function () {
    Route::get('/', function () {
        return view('landing');
    })->name('landing');
});

/*
|--------------------------------------------------------------------------
| App Domain (app.chatme.com.mx)
|--------------------------------------------------------------------------
| SaaS application. Auth routes provided by Fortify (scoped via config).
| Tenant middleware applied per-route as needed.
*/

Route::domain('app.' . config('app.base_domain'))->group(function () {
    Route::prefix('health')->group(function () {
        Route::get('/app', [HealthCheckController::class, 'app']);
        Route::get('/db', [HealthCheckController::class, 'db']);
        Route::get('/redis', [HealthCheckController::class, 'redis']);
        Route::get('/queue', [HealthCheckController::class, 'queue']);
    });

    Route::middleware([
        'auth:sanctum',
        config('jetstream.auth_session'),
        'verified',
    ])->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');
    });
});
