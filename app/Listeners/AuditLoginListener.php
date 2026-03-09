<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;

class AuditLoginListener
{
    public function __construct(private AuditService $auditService)
    {
    }

    public function handleLogin(Login $event): void
    {
        $this->auditService->log(
            action: 'auth.login',
            organizationId: $event->user->organization_id,
            userId: $event->user->id,
            request: request(),
        );
    }

    public function handleFailed(Failed $event): void
    {
        $this->auditService->log(
            action: 'auth.failed',
            newValues: ['email' => $event->credentials['email'] ?? 'unknown'],
            request: request(),
        );
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        // Skip if user was deleted (e.g. account deletion flow)
        if (! \App\Models\User::where('id', $event->user->id)->exists()) {
            return;
        }

        $this->auditService->log(
            action: 'auth.logout',
            organizationId: $event->user->organization_id,
            userId: $event->user->id,
            request: request(),
        );
    }
}
