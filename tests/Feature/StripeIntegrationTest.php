<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Services\StripeService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StripeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([\Illuminate\Broadcasting\BroadcastEvent::class]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansAndFeaturesSeeder::class);

        $this->org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org', 'status' => 'active']);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('org_admin');

        $this->plan = Plan::where('slug', 'professional')->first();

        OrganizationSubscription::create([
            'organization_id' => $this->org->id,
            'plan_id' => Plan::where('slug', 'starter')->first()->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        app()->instance('tenant', $this->org);
    }

    // -- StripeService Unit Tests --

    public function test_stripe_not_configured_by_default(): void
    {
        $service = app(StripeService::class);
        $this->assertFalse($service->isConfigured());
    }

    public function test_checkout_returns_null_without_config(): void
    {
        $service = app(StripeService::class);
        $result = $service->createCheckoutSession(
            $this->org, $this->plan, 'monthly', 'http://test/success', 'http://test/cancel'
        );
        $this->assertNull($result);
    }

    public function test_portal_returns_null_without_config(): void
    {
        $service = app(StripeService::class);
        $result = $service->createPortalSession($this->org, 'http://test/return');
        $this->assertNull($result);
    }

    public function test_cancel_returns_null_without_config(): void
    {
        $service = app(StripeService::class);
        $result = $service->cancelSubscription('sub_123');
        $this->assertNull($result);
    }

    // -- Checkout Fallback (no Stripe) --

    public function test_checkout_falls_back_to_plan_change(): void
    {
        $response = $this->actingAs($this->admin)->post(route('billing.checkout'), [
            'plan_id' => $this->plan->id,
            'cycle' => 'monthly',
        ]);

        $response->assertRedirect(route('billing.index'));
        $sub = OrganizationSubscription::withoutGlobalScopes()
            ->where('organization_id', $this->org->id)
            ->latest()
            ->first();
        $this->assertEquals($this->plan->id, $sub->plan_id);
    }

    // -- Cancel Subscription --

    public function test_cancel_subscription(): void
    {
        $response = $this->actingAs($this->admin)->post(route('billing.cancel'));

        $response->assertRedirect(route('billing.index'));
        $sub = OrganizationSubscription::withoutGlobalScopes()
            ->where('organization_id', $this->org->id)
            ->latest()
            ->first();
        $this->assertEquals('canceled', $sub->status);
        $this->assertNotNull($sub->canceled_at);
    }

    // -- Webhook Tests --

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test123']);

        $response = $this->postJson('/api/webhooks/stripe', ['type' => 'test'], [
            'Stripe-Signature' => 'invalid',
        ]);

        $response->assertStatus(400);
    }

    public function test_webhook_accepts_valid_signature(): void
    {
        $webhookSecret = 'whsec_test123';
        config(['services.stripe.webhook_secret' => $webhookSecret]);

        $payload = json_encode([
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_nonexistent',
                    'status' => 'active',
                ],
            ],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $webhookSecret);

        $response = $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $response->assertJson(['received' => true]);
    }

    public function test_webhook_checkout_completed_creates_subscription(): void
    {
        $webhookSecret = 'whsec_test123';
        config(['services.stripe.webhook_secret' => $webhookSecret]);

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123',
                    'metadata' => [
                        'organization_id' => $this->org->id,
                        'plan_id' => $this->plan->id,
                        'billing_cycle' => 'monthly',
                    ],
                ],
            ],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $webhookSecret);

        $response = $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();

        $this->assertDatabaseHas('organization_subscriptions', [
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
            'stripe_customer_id' => 'cus_test123',
            'stripe_subscription_id' => 'sub_test123',
            'status' => 'active',
        ]);
    }

    public function test_webhook_subscription_deleted_cancels(): void
    {
        $webhookSecret = 'whsec_test123';
        config(['services.stripe.webhook_secret' => $webhookSecret]);

        $sub = OrganizationSubscription::create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'stripe_customer_id' => 'cus_test123',
            'stripe_subscription_id' => 'sub_test456',
        ]);

        $payload = json_encode([
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => 'sub_test456',
                ],
            ],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $webhookSecret);

        $response = $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $sub->refresh();
        $this->assertEquals('canceled', $sub->status);
        $this->assertNotNull($sub->grace_period_ends_at);
    }

    public function test_webhook_payment_failed_sets_past_due(): void
    {
        $webhookSecret = 'whsec_test123';
        config(['services.stripe.webhook_secret' => $webhookSecret]);

        $sub = OrganizationSubscription::create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'stripe_customer_id' => 'cus_test123',
            'stripe_subscription_id' => 'sub_test789',
        ]);

        $payload = json_encode([
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'subscription' => 'sub_test789',
                ],
            ],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $webhookSecret);

        $response = $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $sub->refresh();
        $this->assertEquals('past_due', $sub->status);
    }

    public function test_webhook_payment_succeeded_reactivates(): void
    {
        $webhookSecret = 'whsec_test123';
        config(['services.stripe.webhook_secret' => $webhookSecret]);

        $sub = OrganizationSubscription::create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
            'status' => 'past_due',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'stripe_customer_id' => 'cus_test123',
            'stripe_subscription_id' => 'sub_test000',
        ]);

        $payload = json_encode([
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'subscription' => 'sub_test000',
                ],
            ],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $webhookSecret);

        $response = $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $sub->refresh();
        $this->assertEquals('active', $sub->status);
    }

    // -- Permission Tests --

    public function test_non_admin_cannot_checkout(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $response = $this->actingAs($agent)->post(route('billing.checkout'), [
            'plan_id' => $this->plan->id,
            'cycle' => 'monthly',
        ]);

        $response->assertForbidden();
    }

    public function test_billing_page_shows_stripe_status(): void
    {
        $response = $this->actingAs($this->admin)->get(route('billing.index'));

        $response->assertOk();
        $response->assertViewHas('stripeConfigured', false);
    }
}
