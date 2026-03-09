<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    public function log(
        string $action,
        ?int $organizationId = null,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
    ): AuditLog {
        if (! config('security.audit_enabled', true)) {
            return new AuditLog();
        }

        return AuditLog::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }

    public function logAuth(string $action, Request $request, ?int $userId = null): AuditLog
    {
        return $this->log(
            action: $action,
            userId: $userId,
            request: $request,
        );
    }

    public function logModelChange(
        string $action,
        object $model,
        ?array $oldValues = null,
        ?Request $request = null,
    ): AuditLog {
        $userId = $request?->user()?->id ?? auth()->id();
        $organizationId = $model->organization_id ?? null;

        return $this->log(
            action: $action,
            organizationId: $organizationId,
            userId: $userId,
            entityType: class_basename($model),
            entityId: $model->id ?? null,
            oldValues: $oldValues,
            newValues: $model instanceof \Illuminate\Database\Eloquent\Model ? $model->getChanges() : null,
            request: $request,
        );
    }
}
