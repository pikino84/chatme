<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeService
{
    private const API_BASE = 'https://api.stripe.com/v1';

    private ?string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret_key');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->secretKey);
    }

    public function createCheckoutSession(
        Organization $organization,
        Plan $plan,
        string $cycle,
        string $successUrl,
        string $cancelUrl,
    ): ?array {
        if (! $this->isConfigured()) {
            return null;
        }

        $customerId = $this->getOrCreateCustomer($organization);
        if (! $customerId) {
            return null;
        }

        $price = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
        $interval = $cycle === 'yearly' ? 'year' : 'month';

        $response = $this->post('/checkout/sessions', [
            'customer' => $customerId,
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'price_data' => [
                    'currency' => config('services.stripe.currency', 'mxn'),
                    'unit_amount' => $price,
                    'recurring' => ['interval' => $interval],
                    'product_data' => [
                        'name' => $plan->name,
                        'description' => $plan->description ?? '',
                    ],
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $cycle,
            ],
        ]);

        if (! $response) {
            return null;
        }

        return [
            'session_id' => $response['id'],
            'url' => $response['url'],
        ];
    }

    public function createPortalSession(Organization $organization, string $returnUrl): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $subscription = OrganizationSubscription::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotNull('stripe_customer_id')
            ->latest()
            ->first();

        if (! $subscription || ! $subscription->stripe_customer_id) {
            return null;
        }

        $response = $this->post('/billing_portal/sessions', [
            'customer' => $subscription->stripe_customer_id,
            'return_url' => $returnUrl,
        ]);

        if (! $response) {
            return null;
        }

        return [
            'url' => $response['url'],
        ];
    }

    public function cancelSubscription(string $stripeSubscriptionId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return $this->delete("/subscriptions/{$stripeSubscriptionId}");
    }

    public function getOrCreateCustomer(Organization $organization): ?string
    {
        // Check if org already has a stripe customer
        $subscription = OrganizationSubscription::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotNull('stripe_customer_id')
            ->latest()
            ->first();

        if ($subscription?->stripe_customer_id) {
            return $subscription->stripe_customer_id;
        }

        // Create a new Stripe customer
        $response = $this->post('/customers', [
            'name' => $organization->name,
            'metadata' => [
                'organization_id' => $organization->id,
                'slug' => $organization->slug,
            ],
        ]);

        return $response['id'] ?? null;
    }

    public function constructWebhookEvent(string $payload, string $signature): ?array
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        if (! $webhookSecret) {
            return null;
        }

        // Verify signature
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signature) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 't=')) {
                $timestamp = substr($part, 2);
            } elseif (str_starts_with($part, 'v1=')) {
                $signatures[] = substr($part, 3);
            }
        }

        if (! $timestamp || empty($signatures)) {
            return null;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        $valid = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                $valid = true;
                break;
            }
        }

        if (! $valid) {
            Log::warning('Stripe webhook signature verification failed');
            return null;
        }

        return json_decode($payload, true);
    }

    private function post(string $path, array $data): ?array
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->asForm()
                ->timeout(30)
                ->post(self::API_BASE . $path, $this->flattenArray($data));

            if (! $response->successful()) {
                Log::error('Stripe API error', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Stripe API request failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function delete(string $path): ?array
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->timeout(30)
                ->delete(self::API_BASE . $path);

            if (! $response->successful()) {
                Log::error('Stripe API error', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Stripe API request failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Flatten nested array to Stripe form format: line_items[0][price_data][currency]=mxn
     */
    private function flattenArray(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
