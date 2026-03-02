<?php

use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\ConversationsController;
use App\Http\Controllers\Tenant\DealBoardController;
use App\Http\Controllers\Tenant\DealController;
use App\Http\Controllers\Tenant\InboxController;
use App\Http\Controllers\Tenant\AiConfigController;
use App\Http\Controllers\Tenant\KbArticleController;
use App\Http\Controllers\Tenant\KbCategoryController;
use App\Http\Controllers\Tenant\MessageController;
use App\Http\Controllers\Tenant\ChannelController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Controllers\Tenant\TeamController;
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

            // CRM Kanban
            Route::get('/deals', [DealBoardController::class, 'index'])->name('deals.board');
            Route::get('/deals/{deal}', [DealBoardController::class, 'show'])->name('deals.show');
            Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
            Route::post('/deals/{deal}/move', [DealController::class, 'move'])->name('deals.move');
            Route::post('/deals/{deal}/assign', [DealController::class, 'assign'])->name('deals.assign');
            Route::post('/deals/{deal}/notes', [DealController::class, 'addNote'])->name('deals.notes.store');

            // Tenant Settings
            Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
            Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::get('/settings/team', [TeamController::class, 'index'])->name('settings.team');
            Route::post('/settings/team/{user}/role', [TeamController::class, 'updateRole'])->name('settings.team.role');
            Route::post('/settings/team/{user}/toggle', [TeamController::class, 'toggleActive'])->name('settings.team.toggle');

            // Billing
            Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
            Route::get('/billing/plans', [BillingController::class, 'plans'])->name('billing.plans');
            Route::post('/billing/change-plan', [BillingController::class, 'changePlan'])->name('billing.change-plan');

            // Knowledge Base
            Route::get('/kb/categories', [KbCategoryController::class, 'index'])->name('kb.categories');
            Route::post('/kb/categories', [KbCategoryController::class, 'store'])->name('kb.categories.store');
            Route::post('/kb/categories/{category}/update', [KbCategoryController::class, 'update'])->name('kb.categories.update');
            Route::post('/kb/categories/{category}/delete', [KbCategoryController::class, 'destroy'])->name('kb.categories.destroy');

            Route::get('/kb/articles', [KbArticleController::class, 'index'])->name('kb.articles');
            Route::get('/kb/articles/create', [KbArticleController::class, 'create'])->name('kb.articles.create');
            Route::post('/kb/articles', [KbArticleController::class, 'store'])->name('kb.articles.store');
            Route::get('/kb/articles/{article}', [KbArticleController::class, 'show'])->name('kb.articles.show');
            Route::get('/kb/articles/{article}/edit', [KbArticleController::class, 'edit'])->name('kb.articles.edit');
            Route::post('/kb/articles/{article}/update', [KbArticleController::class, 'update'])->name('kb.articles.update');
            Route::post('/kb/articles/{article}/publish', [KbArticleController::class, 'publish'])->name('kb.articles.publish');
            Route::post('/kb/articles/{article}/archive', [KbArticleController::class, 'archive'])->name('kb.articles.archive');
            Route::post('/kb/articles/{article}/delete', [KbArticleController::class, 'destroy'])->name('kb.articles.destroy');

            // Channels
            Route::get('/settings/channels', [ChannelController::class, 'index'])->name('settings.channels');
            Route::get('/settings/channels/create', [ChannelController::class, 'create'])->name('settings.channels.create');
            Route::post('/settings/channels', [ChannelController::class, 'store'])->name('settings.channels.store');
            Route::get('/settings/channels/{channel}', [ChannelController::class, 'show'])->name('settings.channels.show');
            Route::get('/settings/channels/{channel}/edit', [ChannelController::class, 'edit'])->name('settings.channels.edit');
            Route::post('/settings/channels/{channel}/update', [ChannelController::class, 'update'])->name('settings.channels.update');
            Route::post('/settings/channels/{channel}/toggle', [ChannelController::class, 'toggleActive'])->name('settings.channels.toggle');
            Route::post('/settings/channels/{channel}/delete', [ChannelController::class, 'destroy'])->name('settings.channels.delete');

            // AI Configuration
            Route::get('/settings/ai', [AiConfigController::class, 'show'])->name('settings.ai');
            Route::post('/settings/ai', [AiConfigController::class, 'update'])->name('settings.ai.update');

            // Tenant aliases
            Route::get('/tenant/inbox', [InboxController::class, 'index'])->name('tenant.inbox');
            Route::get('/tenant/kanban', [DealBoardController::class, 'index'])->name('tenant.kanban');
            Route::get('/tenant/settings', [SettingsController::class, 'show'])->name('tenant.settings');
            Route::get('/tenant/dashboard', function () {
                return view('tenant.dashboard');
            })->name('tenant.dashboard');
        });
    });
});
