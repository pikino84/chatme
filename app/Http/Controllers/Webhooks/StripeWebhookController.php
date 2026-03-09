<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Services\BillingService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private BillingService $billingService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        $event = $this->stripeService->constructWebhookEvent($payload, $signature);

        if (! $event) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $type = $event['type'] ?? '';
        $data = $event['data']['object'] ?? [];

        Log::info('Stripe webhook received', ['type' => $type]);

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($data),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($data),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($data),
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($data),
            'invoice.payment_failed' => $this->handlePaymentFailed($data),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function handleCheckoutCompleted(array $data): void
    {
        $metadata = $data['metadata'] ?? [];
        $organizationId = $metadata['organization_id'] ?? null;
        $planId = $metadata['plan_id'] ?? null;
        $cycle = $metadata['billing_cycle'] ?? 'monthly';

        if (! $organizationId || ! $planId) {
            Log::warning('Stripe checkout.session.completed missing metadata', $metadata);
            return;
        }

        $org = Organization::find($organizationId);
        $plan = Plan::find($planId);

        if (! $org || ! $plan) {
            return;
        }

        $stripeCustomerId = $data['customer'] ?? null;
        $stripeSubscriptionId = $data['subscription'] ?? null;

        // Cancel any existing manual subscription
        $existing = $this->billingService->getActiveSubscription($org);
        if ($existing && $existing->isManual()) {
            $existing->update(['status' => 'canceled', 'canceled_at' => now()]);
        }

        // Create new subscription with Stripe IDs
        $endsAt = $cycle === 'yearly' ? now()->addYear() : now()->addMonth();

        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $cycle,
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'stripe_customer_id' => $stripeCustomerId,
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);

        Log::info('Stripe subscription created', [
            'organization_id' => $org->id,
            'plan' => $plan->name,
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);
    }

    private function handleSubscriptionUpdated(array $data): void
    {
        $stripeSubId = $data['id'] ?? null;
        if (! $stripeSubId) {
            return;
        }

        $subscription = OrganizationSubscription::withoutGlobalScopes()
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();

        if (! $subscription) {
            return;
        }

        $status = $data['status'] ?? null;

        $mappedStatus = match ($status) {
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due' => 'past_due',
            'canceled', 'unpaid', 'incomplete_expired' => 'canceled',
            default => null,
        };

        if ($mappedStatus) {
            $update = ['status' => $mappedStatus];

            if ($mappedStatus === 'canceled') {
                $update['canceled_at'] = now();
                $update['grace_period_ends_at'] = now()->addDays(7);
            }

            $currentPeriodEnd = $data['current_period_end'] ?? null;
            if ($currentPeriodEnd) {
                $update['ends_at'] = \Carbon\Carbon::createFromTimestamp($currentPeriodEnd);
            }

            $subscription->update($update);
        }
    }

    private function handleSubscriptionDeleted(array $data): void
    {
        $stripeSubId = $data['id'] ?? null;
        if (! $stripeSubId) {
            return;
        }

        $subscription = OrganizationSubscription::withoutGlobalScopes()
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();

        if ($subscription) {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'grace_period_ends_at' => now()->addDays(7),
            ]);
        }
    }

    private function handlePaymentSucceeded(array $data): void
    {
        $stripeSubId = $data['subscription'] ?? null;
        if (! $stripeSubId) {
            return;
        }

        $subscription = OrganizationSubscription::withoutGlobalScopes()
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();

        if ($subscription && $subscription->status === 'past_due') {
            $subscription->update(['status' => 'active']);
        }
    }

    private function handlePaymentFailed(array $data): void
    {
        $stripeSubId = $data['subscription'] ?? null;
        if (! $stripeSubId) {
            return;
        }

        $subscription = OrganizationSubscription::withoutGlobalScopes()
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();

        if ($subscription) {
            $subscription->update(['status' => 'past_due']);
        }
    }
}
