<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUsageMonthly;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanFeatureValue;
use App\Models\User;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BillingUxTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Plan $plan;
    private OrganizationSubscription $subscription;
    private User $orgAdmin;
    private User $agent;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::factory()->create();

        $this->plan = Plan::factory()->create();

        $this->subscription = OrganizationSubscription::factory()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
        ]);

        $this->orgAdmin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->orgAdmin->assignRole('org_admin');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        $this->domain = 'http://app.' . config('app.base_domain');

        app()->instance('tenant', $this->org);
        Event::fake();
    }

    private function billingUrl(string $path = ''): string
    {
        return "{$this->domain}/billing{$path}";
    }

    public function test_org_admin_can_view_subscription(): void
    {
        $response = $this->actingAs($this->orgAdmin)->get($this->billingUrl());

        $response->assertOk();
        $response->assertViewIs('billing.subscription');
    }

    public function test_subscription_shows_plan_details(): void
    {
        $response = $this->actingAs($this->orgAdmin)->get($this->billingUrl());

        $response->assertOk();
        $response->assertViewHas('subscription');
        $response->assertViewHas('plan');
    }

    public function test_org_admin_can_view_plans(): void
    {
        $this->seed(PlansAndFeaturesSeeder::class);

        $response = $this->actingAs($this->orgAdmin)->get($this->billingUrl('/plans'));

        $response->assertOk();
        $response->assertViewIs('billing.plans');
        $response->assertViewHas('plans');
        $this->assertGreaterThanOrEqual(3, $response->viewData('plans')->count());
    }

    public function test_org_admin_can_change_plan(): void
    {
        $newPlan = Plan::factory()->create(['name' => 'Pro', 'slug' => 'pro']);

        $response = $this->actingAs($this->orgAdmin)->post($this->billingUrl('/change-plan'), [
            'plan_id' => $newPlan->id,
        ]);

        $response->assertRedirect(route('billing.index'));
        $this->assertEquals($newPlan->id, $this->subscription->fresh()->plan_id);
    }

    public function test_usage_meters_display(): void
    {
        $feature = PlanFeature::create([
            'code' => 'max_conversations_monthly',
            'description' => 'Monthly conversations',
            'type' => 'limit',
        ]);

        PlanFeatureValue::create([
            'plan_id' => $this->plan->id,
            'plan_feature_id' => $feature->id,
            'value' => '100',
        ]);

        OrganizationUsageMonthly::create([
            'organization_id' => $this->org->id,
            'feature_code' => 'max_conversations_monthly',
            'period' => now()->format('Y-m'),
            'usage' => 42,
        ]);

        $response = $this->actingAs($this->orgAdmin)->get($this->billingUrl());

        $response->assertOk();
        $limits = $response->viewData('limits');
        $this->assertCount(1, $limits);
        $this->assertEquals(42, $limits->first()['usage']);
        $this->assertEquals(100, $limits->first()['limit']);
    }

    public function test_agent_cannot_access_billing(): void
    {
        $response = $this->actingAs($this->agent)->get($this->billingUrl());

        $response->assertForbidden();
    }

    public function test_no_subscription_handled(): void
    {
        $this->subscription->delete();

        $response = $this->actingAs($this->orgAdmin)->get($this->billingUrl());

        $response->assertOk();
        $response->assertViewHas('subscription', null);
    }
}
