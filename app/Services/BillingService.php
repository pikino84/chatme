<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUsageMonthly;
use App\Models\Plan;
use App\Models\PlanFeature;

class BillingService
{
    public function subscribe(
        Organization $organization,
        Plan $plan,
        string $cycle = 'monthly',
        bool $withTrial = false,
    ): OrganizationSubscription {
        $startsAt = now();
        $endsAt = $cycle === 'yearly' ? now()->addYear() : now()->addMonth();

        $data = [
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => $withTrial && $plan->trial_days > 0 ? 'trialing' : 'active',
            'billing_cycle' => $cycle,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $withTrial && $plan->trial_days > 0
                ? now()->addDays($plan->trial_days)
                : null,
        ];

        return OrganizationSubscription::create($data);
    }

    public function cancel(Organization $organization): ?OrganizationSubscription
    {
        $subscription = $this->getActiveSubscription($organization);

        if (!$subscription) {
            return null;
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'grace_period_ends_at' => $subscription->ends_at ?? now()->addDays(7),
        ]);

        return $subscription->fresh();
    }

    public function changePlan(Organization $organization, Plan $newPlan): ?OrganizationSubscription
    {
        $current = $this->getActiveSubscription($organization);

        if (!$current) {
            return $this->subscribe($organization, $newPlan);
        }

        $current->update([
            'plan_id' => $newPlan->id,
        ]);

        return $current->fresh();
    }

    public function getActiveSubscription(Organization $organization): ?OrganizationSubscription
    {
        return OrganizationSubscription::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['active', 'trialing', 'canceled'])
            ->latest()
            ->first();
    }

    public function hasAccess(Organization $organization): bool
    {
        $subscription = $this->getActiveSubscription($organization);

        return $subscription && $subscription->hasAccess();
    }

    public function isReadOnly(Organization $organization): bool
    {
        $subscription = $this->getActiveSubscription($organization);

        if (!$subscription) {
            return true;
        }

        return $subscription->isReadOnly();
    }

    public function checkFeature(Organization $organization, string $featureCode): bool
    {
        $subscription = $this->getActiveSubscription($organization);

        if (!$subscription || !$subscription->hasAccess()) {
            return false;
        }

        $feature = PlanFeature::where('code', $featureCode)->first();

        if (!$feature) {
            return false;
        }

        if (!$feature->isBoolean()) {
            return false;
        }

        $value = $subscription->plan->getFeatureValue($featureCode);

        return $value === 'true';
    }

    public function checkLimit(Organization $organization, string $featureCode): bool
    {
        $subscription = $this->getActiveSubscription($organization);

        if (!$subscription || !$subscription->hasAccess()) {
            return false;
        }

        $value = $subscription->plan->getFeatureValue($featureCode);

        if (!$value) {
            return false;
        }

        if ($value === 'unlimited') {
            return true;
        }

        $limit = (int) $value;
        $currentUsage = $this->getUsage($organization, $featureCode);

        return $currentUsage < $limit;
    }

    public function incrementUsage(Organization $organization, string $featureCode, int $amount = 1): void
    {
        $period = now()->format('Y-m');

        $record = OrganizationUsageMonthly::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('feature_code', $featureCode)
            ->where('period', $period)
            ->first();

        if ($record) {
            $record->increment('usage', $amount);
        } else {
            OrganizationUsageMonthly::create([
                'organization_id' => $organization->id,
                'feature_code' => $featureCode,
                'period' => $period,
                'usage' => $amount,
            ]);
        }
    }

    public function getUsage(Organization $organization, string $featureCode, ?string $period = null): int
    {
        $period = $period ?? now()->format('Y-m');

        $record = OrganizationUsageMonthly::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('feature_code', $featureCode)
            ->where('period', $period)
            ->first();

        return $record?->usage ?? 0;
    }
}
