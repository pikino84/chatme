<?php

use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

Route::prefix('health')->group(function () {
    Route::get('/app', [HealthCheckController::class, 'app']);
    Route::get('/db', [HealthCheckController::class, 'db']);
    Route::get('/redis', [HealthCheckController::class, 'redis']);
    Route::get('/queue', [HealthCheckController::class, 'queue']);
});

Route::get('/', function () {
    return view('welcome');
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
