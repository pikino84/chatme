<?php

use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\Tenant\ConversationsController;
use App\Http\Controllers\Tenant\InboxController;
use App\Http\Controllers\Tenant\MessageController;
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
    Route::get('/', function () {
        return redirect()->route('login');
    });

    Route::prefix('health')->group(function () {
        Route::get('/app', [HealthCheckController::class, 'app']);
        Route::get('/db', [HealthCheckController::class, 'db']);
        Route::get('/redis', [HealthCheckController::class, 'redis']);
        Route::get('/queue', [HealthCheckController::class, 'queue']);
    });

    Route::middleware(['auth'])->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

        Route::middleware([\App\Http\Middleware\ResolveUserTenant::class])->group(function () {
            Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
            Route::get('/inbox/conversations/{conversation}', [ConversationsController::class, 'show'])->name('inbox.conversations.show');
            Route::post('/inbox/conversations/{conversation}/read', [ConversationsController::class, 'markAsRead'])->name('inbox.conversations.read');
            Route::post('/inbox/conversations/{conversation}/close', [ConversationsController::class, 'close'])->name('inbox.conversations.close');
            Route::post('/inbox/conversations/{conversation}/reopen', [ConversationsController::class, 'reopen'])->name('inbox.conversations.reopen');
            Route::post('/inbox/conversations/{conversation}/assign', [ConversationsController::class, 'assign'])->name('inbox.conversations.assign');
            Route::post('/inbox/conversations/{conversation}/transfer', [ConversationsController::class, 'transfer'])->name('inbox.conversations.transfer');
            Route::post('/inbox/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('inbox.conversations.messages.store');
        });
    });
});
