<?php

namespace App\Providers;

use App\Events\ConversationCreated;
use App\Events\MessageReceivedEvent;
use App\Listeners\AuditLoginListener;
use App\Listeners\AutomationListener;
use App\Listeners\BusinessHoursConversationCreatedListener;
use App\Listeners\BusinessHoursMessageReceivedListener;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        $this->registerAuditListeners();
        $this->registerAutomationListeners();
    }

    private function registerAuditListeners(): void
    {
        Event::listen(Login::class, [AuditLoginListener::class, 'handleLogin']);
        Event::listen(Failed::class, [AuditLoginListener::class, 'handleFailed']);
        Event::listen(Logout::class, [AuditLoginListener::class, 'handleLogout']);
    }

    private function registerAutomationListeners(): void
    {
        Event::listen(ConversationCreated::class, AutomationListener::class);
        Event::listen(ConversationCreated::class, BusinessHoursConversationCreatedListener::class);
        Event::listen(MessageReceivedEvent::class, BusinessHoursMessageReceivedListener::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('tenant-api', function (Request $request) {
            $key = $request->user()?->organization_id ?? $request->ip();
            $limit = config('security.tenant_api_rate_limit', 60);
            return Limit::perMinute($limit)->by('tenant-api:' . $key);
        });

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
