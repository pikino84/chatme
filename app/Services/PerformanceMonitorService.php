<?php

namespace App\Services;

use App\Models\OrganizationUsageMonthly;
use App\Models\SaasAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PerformanceMonitorService
{
    private int $queueBacklogThreshold = 100;
    private int $failedJobsThreshold = 10;
    private float $usagePercentThreshold = 0.9;

    public function runAllChecks(): array
    {
        return [
            'queue_backlog' => $this->checkQueueBacklog(),
            'failed_jobs' => $this->checkFailedJobs(),
            'usage_limits' => $this->checkUsageLimits(),
        ];
    }

    public function checkQueueBacklog(): bool
    {
        try {
            $size = (int) Redis::llen('queues:critical')
                  + (int) Redis::llen('queues:default')
                  + (int) Redis::llen('queues:low');
        } catch (\Throwable $e) {
            Log::warning('PerformanceMonitor: Redis unavailable for queue check', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        if ($size >= $this->queueBacklogThreshold) {
            $this->createAlertIfNotExists(
                'warning',
                'Queue Backlog Alert',
                "Queue backlog has reached {$size} jobs (threshold: {$this->queueBacklogThreshold})."
            );
            return true;
        }

        return false;
    }

    public function checkFailedJobs(): bool
    {
        $count = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subHour())
            ->count();

        if ($count >= $this->failedJobsThreshold) {
            $this->createAlertIfNotExists(
                'critical',
                'Failed Jobs Alert',
                "{$count} jobs failed in the last hour (threshold: {$this->failedJobsThreshold})."
            );
            return true;
        }

        return false;
    }

    public function checkUsageLimits(): int
    {
        $period = now()->format('Y-m');
        $alertCount = 0;

        $usages = OrganizationUsageMonthly::withoutGlobalScopes()
            ->where('period', $period)
            ->with(['organization.subscription.plan'])
            ->get();

        foreach ($usages as $usage) {
            $org = $usage->organization;
            if (!$org || !$org->subscription) {
                continue;
            }

            $limit = $org->subscription->plan->getFeatureValue($usage->feature_code);

            if (!$limit || strtolower($limit) === 'unlimited') {
                continue;
            }

            $limitInt = (int) $limit;
            if ($limitInt > 0 && ($usage->usage / $limitInt) >= $this->usagePercentThreshold) {
                $this->createAlertIfNotExists(
                    'warning',
                    "Usage Limit Warning: {$org->name}",
                    "{$org->name} has used {$usage->usage}/{$limitInt} of {$usage->feature_code} this period.",
                    $org->id
                );
                $alertCount++;
            }
        }

        return $alertCount;
    }

    private function createAlertIfNotExists(
        string $type,
        string $title,
        string $message,
        ?int $organizationId = null
    ): void {
        $exists = SaasAlert::where('title', $title)
            ->where('is_active', true)
            ->whereNull('resolved_at')
            ->where('organization_id', $organizationId)
            ->exists();

        if (!$exists) {
            SaasAlert::create([
                'organization_id' => $organizationId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'is_active' => true,
                'created_by' => null,
            ]);
        }
    }
}
