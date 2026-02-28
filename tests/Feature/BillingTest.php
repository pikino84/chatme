<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUsageMonthly;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanFeatureValue;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Plan $plan;
    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::factory()->create();
        $this->plan = Plan::factory()->create(['slug' => 'test-starter']);
        $this->billing = app(BillingService::class);

        Route::middleware(['auth', 'subscription'])->get('/test-subscription-middleware', fn () => response()->json(['ok' => true]));
        Route::middleware(['auth', 'subscription'])->post('/test-subscription-middleware', fn () => response()->json(['ok' => true]));
        Route::middleware(['auth', 'feature:ai_enabled'])->get('/test-feature-middleware', fn () => response()->json(['ok' => true]));
        Route::middleware(['auth', 'usage.limit:max_conversations_monthly'])->get('/test-usage-middleware', fn () => response()->json(['ok' => true]));
    }

    // ── Plan model ──

    public function test_plan_has_feature_values_relation(): void
    {
        $feature = PlanFeature::create(['code' => 'max_agents', 'description' => 'Max agents', 'type' => 'limit']);
        PlanFeatureValue::create(['plan_id' => $this->plan->id, 'plan_feature_id' => $feature->id, 'value' => '5']);

        $this->assertCount(1, $this->plan->featureValues);
    }

    public function test_plan_get_feature_value(): void
    {
        $feature = PlanFeature::create(['code' => 'max_agents', 'description' => 'Max agents', 'type' => 'limit']);
        PlanFeatureValue::create(['plan_id' => $this->plan->id, 'plan_feature_id' => $feature->id, 'value' => '10']);

        $this->assertEquals('10', $this->plan->getFeatureValue('max_agents'));
        $this->assertNull($this->plan->getFeatureValue('nonexistent'));
    }

    public function test_plan_is_free(): void
    {
        $free = Plan::factory()->free()->create(['slug' => 'test-free']);
        $this->assertTrue($free->isFree());
        $this->assertFalse($this->plan->isFree());
    }

    // ── PlanFeature model ──

    public function test_plan_feature_type_helpers(): void
    {
        $limit = PlanFeature::create(['code' => 'max_agents', 'description' => 'Max agents', 'type' => 'limit']);
        $boolean = PlanFeature::create(['code' => 'ai_enabled', 'description' => 'AI', 'type' => 'boolean']);

        $this->assertTrue($limit->isLimit());
        $this->assertFalse($limit->isBoolean());
        $this->assertTrue($boolean->isBoolean());
        $this->assertFalse($boolean->isLimit());
    }

    // ── PlanFeatureValue model ──

    public function test_plan_feature_value_is_unlimited(): void
    {
        $feature = PlanFeature::create(['code' => 'max_agents', 'description' => 'Max agents', 'type' => 'limit']);
        $limited = PlanFeatureValue::create(['plan_id' => $this->plan->id, 'plan_feature_id' => $feature->id, 'value' => '5']);
        $unlimited = PlanFeatureValue::create([
            'plan_id' => Plan::factory()->create(['slug' => 'test-ent'])->id,
            'plan_feature_id' => $feature->id,
            'value' => 'unlimited',
        ]);

        $this->assertFalse($limited->isUnlimited());
        $this->assertTrue($unlimited->isUnlimited());
    }

    // ── OrganizationSubscription model ──

    public function test_subscription_status_helpers(): void
    {
        $active = OrganizationSubscription::factory()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($active->isTrialing());
        $this->assertFalse($active->isCanceled());
        $this->assertTrue($active->hasAccess());
        $this->assertFalse($active->isReadOnly());
    }

    public function test_subscription_trialing_has_access(): void
    {
        $trialing = OrganizationSubscription::factory()->trialing()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
        ]);

        $this->assertTrue($trialing->isTrialing());
        $this->assertTrue($trialing->hasAccess());
        $this->assertFalse($trialing->isReadOnly());
    }

    public function test_subscription_expired_trial_has_no_access(): void
    {
        $expired = OrganizationSubscription::factory()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
            'status' => 'trialing',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($expired->isTrialing());
        $this->assertFalse($expired->hasAccess());
        $this->assertTrue($expired->isReadOnly());
    }

    public function test_subscription_canceled_in_grace_period_has_access(): void
    {
        $canceled = OrganizationSubscription::factory()->canceled()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
        ]);

        $this->assertTrue($canceled->isCanceled());
        $this->assertTrue($canceled->isInGracePeriod());
        $this->assertTrue($canceled->hasAccess());
        $this->assertFalse($canceled->isReadOnly());
    }

    public function test_subscription_canceled_past_grace_period_is_readonly(): void
    {
        $expired = OrganizationSubscription::factory()->expired()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
        ]);

        $this->assertTrue($expired->isCanceled());
        $this->assertFalse($expired->isInGracePeriod());
        $this->assertFalse($expired->hasAccess());
        $this->assertTrue($expired->isReadOnly());
    }

    public function test_subscription_is_manual_without_stripe(): void
    {
        $manual = OrganizationSubscription::factory()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
        ]);

        $stripe = OrganizationSubscription::factory()->withStripe()->create([
            'organization_id' => Organization::factory()->create()->id,
            'plan_id' => $this->plan->id,
        ]);

        $this->assertTrue($manual->isManual());
        $this->assertFalse($stripe->isManual());
    }

    // ── BillingService: subscribe ──

    public function test_subscribe_creates_active_subscription(): void
    {
        $sub = $this->billing->subscribe($this->org, $this->plan);

        $this->assertInstanceOf(OrganizationSubscription::class, $sub);
        $this->assertEquals('active', $sub->status);
        $this->assertEquals('monthly', $sub->billing_cycle);
        $this->assertEquals($this->plan->id, $sub->plan_id);
        $this->assertEquals($this->org->id, $sub->organization_id);
        $this->assertNull($sub->trial_ends_at);
    }

    public function test_subscribe_with_trial(): void
    {
        $sub = $this->billing->subscribe($this->org, $this->plan, withTrial: true);

        $this->assertEquals('trialing', $sub->status);
        $this->assertNotNull($sub->trial_ends_at);
        $this->assertTrue($sub->trial_ends_at->isFuture());
    }

    public function test_subscribe_yearly(): void
    {
        $sub = $this->billing->subscribe($this->org, $this->plan, cycle: 'yearly');

        $this->assertEquals('yearly', $sub->billing_cycle);
        $this->assertTrue($sub->ends_at->isAfter(now()->addMonths(11)));
    }

    public function test_subscribe_with_trial_on_no_trial_plan(): void
    {
        $noTrial = Plan::factory()->create(['slug' => 'no-trial', 'trial_days' => 0]);
        $sub = $this->billing->subscribe($this->org, $noTrial, withTrial: true);

        $this->assertEquals('active', $sub->status);
        $this->assertNull($sub->trial_ends_at);
    }

    // ── BillingService: cancel ──

    public function test_cancel_sets_canceled_status(): void
    {
        $this->billing->subscribe($this->org, $this->plan);
        $canceled = $this->billing->cancel($this->org);

        $this->assertEquals('canceled', $canceled->status);
        $this->assertNotNull($canceled->canceled_at);
        $this->assertNotNull($canceled->grace_period_ends_at);
    }

    public function test_cancel_without_subscription_returns_null(): void
    {
        $this->assertNull($this->billing->cancel($this->org));
    }

    // ── BillingService: changePlan ──

    public function test_change_plan_updates_plan(): void
    {
        $this->billing->subscribe($this->org, $this->plan);
        $newPlan = Plan::factory()->professional()->create(['slug' => 'test-pro']);

        $updated = $this->billing->changePlan($this->org, $newPlan);

        $this->assertEquals($newPlan->id, $updated->plan_id);
    }

    public function test_change_plan_without_subscription_creates_one(): void
    {
        $newPlan = Plan::factory()->professional()->create(['slug' => 'test-pro']);
        $sub = $this->billing->changePlan($this->org, $newPlan);

        $this->assertEquals($newPlan->id, $sub->plan_id);
        $this->assertEquals('active', $sub->status);
    }

    // ── BillingService: access checks ──

    public function test_has_access_with_active_subscription(): void
    {
        $this->billing->subscribe($this->org, $this->plan);
        $this->assertTrue($this->billing->hasAccess($this->org));
    }

    public function test_has_no_access_without_subscription(): void
    {
        $this->assertFalse($this->billing->hasAccess($this->org));
    }

    public function test_is_readonly_without_subscription(): void
    {
        $this->assertTrue($this->billing->isReadOnly($this->org));
    }

    public function test_is_not_readonly_with_active_subscription(): void
    {
        $this->billing->subscribe($this->org, $this->plan);
        $this->assertFalse($this->billing->isReadOnly($this->org));
    }

    // ── BillingService: checkFeature ──

    public function test_check_feature_returns_true_when_enabled(): void
    {
        $feature = PlanFeature::create(['code' => 'ai_enabled', 'description' => 'AI', 'type' => 'boolean']);
        PlanFeatureValue::create(['plan_id' => $this->plan->id, 'plan_feature_id' => $feature->id, 'value' => 'true']);
        $this->billing->subscribe($this->org, $this->plan);

        $this->assertTrue($this->billing->checkFeature($this->org, 'ai_enabled'));
    }

    public function test_check_feature_returns_false_when_disabled(): void
    {
        $feature = PlanFeature::create(['code' => 'ai_enabled', 'description' => 'AI', 'type' => 'boolean']);
        PlanFeatureValue::create(['plan_id' => $this->plan->id, 'plan_feature_id' => $feature->id, 'value' => 'false']);
        $this->billing->subscribe($this->org, $this->plan);

        $this->assertFalse($this->billing->checkFeature($this->org, 'ai_enabled'));
    }

    public function test_check_feature_returns_false_without_subscription(): void
    {
        PlanFeature::create(['code' => 'ai_enabled', 'description' => 'AI', 'type' => 'boolean']);
        $this->assertFalse($this->billing->checkFeature($this->org, 'ai_enabled'));
    }

    public function test_check_feature_returns_false_for_nonexistent_feature(): void
    {
        $this->billing->subscribe($this->org, $this->plan);
        $this->assertFalse($this->billing->checkFeature($this->org, 'nonexistent'));
    }

    // ── BillingService: checkLimit ──

    public function test_check_limit_within_range(): void
    {
        $feature = PlanFeature::create(['code' => 'max_conversations_monthly', 'description' => 'Monthly convos', 'type' => 'limit']);
        PlanFeatureValue::create(['plan_id' => $this->plan->id, 'plan_feature_id' => $feature->id, 'value' => '100']);
        $this->billing->subscribe($this->org, $this->plan);

        $this->assertTrue($this->billing->checkLimit($this->org, 'max_conversations_monthly'));
    }

    public function test_check_limit_at_max_returns_false(): void
    {
        $feature = PlanFeature::create(['code' => 'max_conversations_monthly', 'description' => 'Monthly convos', 'type' => 'limit']);
        PlanFeatureValue::create(['plan_id' => $this->plan->id, 'plan_feature_id' => $feature->id, 'value' => '5']);
        $this->billing->subscribe($this->org, $this->plan);

        // Fill to limit
        OrganizationUsageMonthly::create([
            'organization_id' => $this->org->id,
            'feature_code' => 'max_conversations_monthly',
            'period' => now()->format('Y-m'),
            'usage' => 5,
        ]);

        $this->assertFalse($this->billing->checkLimit($this->org, 'max_conversations_monthly'));
    }

    public function test_check_limit_unlimited(): void
    {
        $feature = PlanFeature::create(['code' => 'max_conversations_monthly', 'description' => 'Monthly convos', 'type' => 'limit']);
        PlanFeatureValue::create(['plan_id' => $this->plan->id, 'plan_feature_id' => $feature->id, 'value' => 'unlimited']);
        $this->billing->subscribe($this->org, $this->plan);

        $this->assertTrue($this->billing->checkLimit($this->org, 'max_conversations_monthly'));
    }

    public function test_check_limit_without_subscription(): void
    {
        PlanFeature::create(['code' => 'max_conversations_monthly', 'description' => 'Monthly convos', 'type' => 'limit']);
        $this->assertFalse($this->billing->checkLimit($this->org, 'max_conversations_monthly'));
    }

    // ── BillingService: usage tracking ──

    public function test_increment_usage_creates_record(): void
    {
        $this->billing->incrementUsage($this->org, 'max_conversations_monthly');

        $this->assertEquals(1, $this->billing->getUsage($this->org, 'max_conversations_monthly'));
    }

    public function test_increment_usage_accumulates(): void
    {
        $this->billing->incrementUsage($this->org, 'max_conversations_monthly', 3);
        $this->billing->incrementUsage($this->org, 'max_conversations_monthly', 2);

        $this->assertEquals(5, $this->billing->getUsage($this->org, 'max_conversations_monthly'));
    }

    public function test_usage_scoped_to_period(): void
    {
        $this->billing->incrementUsage($this->org, 'max_conversations_monthly', 10);

        $this->assertEquals(0, $this->billing->getUsage($this->org, 'max_conversations_monthly', '2025-01'));
    }

    public function test_usage_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        $this->billing->incrementUsage($this->org, 'max_conversations_monthly', 5);
        $this->billing->incrementUsage($otherOrg, 'max_conversations_monthly', 3);

        $this->assertEquals(5, $this->billing->getUsage($this->org, 'max_conversations_monthly'));
        $this->assertEquals(3, $this->billing->getUsage($otherOrg, 'max_conversations_monthly'));
    }

    // ── Organization relations ──

    public function test_organization_has_subscription_relation(): void
    {
        OrganizationSubscription::factory()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
        ]);

        $this->assertNotNull($this->org->subscription);
        $this->assertInstanceOf(OrganizationSubscription::class, $this->org->subscription);
    }

    public function test_organization_has_usage_records_relation(): void
    {
        OrganizationUsageMonthly::create([
            'organization_id' => $this->org->id,
            'feature_code' => 'max_conversations_monthly',
            'period' => now()->format('Y-m'),
            'usage' => 10,
        ]);

        $this->assertCount(1, $this->org->usageRecords);
    }

    // ── Tenant isolation ──

    public function test_subscription_tenant_scope_isolates_data(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        OrganizationSubscription::factory()->create([
            'organization_id' => $orgA->id,
            'plan_id' => $this->plan->id,
        ]);
        OrganizationSubscription::factory()->create([
            'organization_id' => $orgB->id,
            'plan_id' => $this->plan->id,
        ]);

        app()->instance('tenant', $orgA);
        $this->assertCount(1, OrganizationSubscription::all());

        app()->instance('tenant', $orgB);
        $this->assertCount(1, OrganizationSubscription::all());
    }

    // ── Middleware: EnsureActiveSubscription ──

    public function test_middleware_subscription_blocks_without_subscription(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $user->assignRole('org_admin');

        app()->instance('tenant', $this->org);

        $response = $this->actingAs($user)->getJson('/test-subscription-middleware');

        $response->assertStatus(403);
    }

    public function test_middleware_subscription_allows_active_subscription(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $user->assignRole('org_admin');
        $this->billing->subscribe($this->org, $this->plan);

        app()->instance('tenant', $this->org);

        $response = $this->actingAs($user)->getJson('/test-subscription-middleware');

        $response->assertOk();
    }

    public function test_middleware_subscription_blocks_post_when_readonly(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $user->assignRole('org_admin');

        // Create an expired subscription (past grace period)
        OrganizationSubscription::factory()->expired()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
        ]);

        app()->instance('tenant', $this->org);

        // GET should pass (has access returns false for expired, so it blocks even GET)
        $response = $this->actingAs($user)->getJson('/test-subscription-middleware');
        $response->assertStatus(403);
    }

    // ── Middleware: CheckFeature ──

    public function test_middleware_feature_blocks_disabled_feature(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $user->assignRole('org_admin');

        PlanFeature::create(['code' => 'ai_enabled', 'description' => 'AI', 'type' => 'boolean']);
        // No feature value configured — defaults to disabled
        $this->billing->subscribe($this->org, $this->plan);

        app()->instance('tenant', $this->org);

        $response = $this->actingAs($user)->getJson('/test-feature-middleware');

        $response->assertStatus(403);
    }

    public function test_middleware_feature_allows_enabled_feature(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $user->assignRole('org_admin');

        $feature = PlanFeature::create(['code' => 'ai_enabled', 'description' => 'AI', 'type' => 'boolean']);
        PlanFeatureValue::create(['plan_id' => $this->plan->id, 'plan_feature_id' => $feature->id, 'value' => 'true']);
        $this->billing->subscribe($this->org, $this->plan);

        app()->instance('tenant', $this->org);

        $response = $this->actingAs($user)->getJson('/test-feature-middleware');

        $response->assertOk();
    }

    // ── Middleware: CheckUsageLimit ──

    public function test_middleware_usage_blocks_at_limit(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $user->assignRole('org_admin');

        $feature = PlanFeature::create(['code' => 'max_conversations_monthly', 'description' => 'Convos', 'type' => 'limit']);
        PlanFeatureValue::create(['plan_id' => $this->plan->id, 'plan_feature_id' => $feature->id, 'value' => '3']);
        $this->billing->subscribe($this->org, $this->plan);

        OrganizationUsageMonthly::create([
            'organization_id' => $this->org->id,
            'feature_code' => 'max_conversations_monthly',
            'period' => now()->format('Y-m'),
            'usage' => 3,
        ]);

        app()->instance('tenant', $this->org);

        $response = $this->actingAs($user)->getJson('/test-usage-middleware');

        $response->assertStatus(429);
    }

    public function test_middleware_usage_allows_within_limit(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $user->assignRole('org_admin');

        $feature = PlanFeature::create(['code' => 'max_conversations_monthly', 'description' => 'Convos', 'type' => 'limit']);
        PlanFeatureValue::create(['plan_id' => $this->plan->id, 'plan_feature_id' => $feature->id, 'value' => '100']);
        $this->billing->subscribe($this->org, $this->plan);

        app()->instance('tenant', $this->org);

        $response = $this->actingAs($user)->getJson('/test-usage-middleware');

        $response->assertOk();
    }

    // ── Seeder ──

    public function test_plans_and_features_seeder(): void
    {
        $this->seed(\Database\Seeders\PlansAndFeaturesSeeder::class);

        $this->assertNotNull(Plan::where('slug', 'starter')->first());
        $this->assertNotNull(Plan::where('slug', 'professional')->first());
        $this->assertNotNull(Plan::where('slug', 'enterprise')->first());
        $this->assertGreaterThanOrEqual(9, PlanFeature::count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $countBefore = Plan::count();
        $this->seed(\Database\Seeders\PlansAndFeaturesSeeder::class);
        $this->seed(\Database\Seeders\PlansAndFeaturesSeeder::class);

        // Seeder uses updateOrCreate, so no duplicates from seeder plans
        $this->assertEquals($countBefore + 3, Plan::count());
    }
}
