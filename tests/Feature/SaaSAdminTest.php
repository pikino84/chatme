<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUsageMonthly;
use App\Models\Plan;
use App\Models\SaasAlert;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaaSAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['organization_id' => null]);
        $this->admin->assignRole('saas_admin');

        $this->domain = 'admin.' . config('app.base_domain');
    }

    private function adminGet(string $uri)
    {
        return $this->actingAs($this->admin)
            ->get("http://{$this->domain}/panel{$uri}");
    }

    private function adminPost(string $uri, array $data = [])
    {
        return $this->actingAs($this->admin)
            ->post("http://{$this->domain}/panel{$uri}", $data);
    }

    private function adminPut(string $uri, array $data = [])
    {
        return $this->actingAs($this->admin)
            ->put("http://{$this->domain}/panel{$uri}", $data);
    }

    private function adminDelete(string $uri)
    {
        return $this->actingAs($this->admin)
            ->delete("http://{$this->domain}/panel{$uri}");
    }

    // ── Middleware: ResolveSaaSAdmin ──

    public function test_middleware_blocks_unauthenticated_user(): void
    {
        $response = $this->get("http://{$this->domain}/panel/organizations");
        $response->assertRedirect();
    }

    public function test_middleware_redirects_non_saas_admin_role(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole('org_admin');

        $response = $this->actingAs($user)
            ->get("http://{$this->domain}/panel/organizations");

        $response->assertRedirect();
    }

    public function test_middleware_blocks_saas_admin_with_organization(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole('saas_admin');

        $response = $this->actingAs($user)
            ->get("http://{$this->domain}/panel/organizations");

        $response->assertStatus(403);
    }

    public function test_middleware_allows_saas_admin_without_organization(): void
    {
        $response = $this->adminGet('/organizations');
        $response->assertOk();
    }

    // ── No tenant scope in backoffice ──

    public function test_backoffice_does_not_bind_tenant(): void
    {
        $this->adminGet('/organizations');
        $this->assertFalse(app()->bound('tenant'));
    }

    // ── Dashboard ──

    public function test_dashboard_shows_stats(): void
    {
        Organization::factory()->count(3)->create(['status' => 'active']);
        Organization::factory()->create(['status' => 'suspended']);

        $response = $this->adminGet('/');

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('Total Organizations');
    }

    // ── Organizations ──

    public function test_organizations_index_lists_all(): void
    {
        $org = Organization::factory()->create(['name' => 'TestCorp']);

        $response = $this->adminGet('/organizations');

        $response->assertOk();
        $response->assertSee('TestCorp');
    }

    public function test_organizations_index_search(): void
    {
        Organization::factory()->create(['name' => 'AlphaCo']);
        Organization::factory()->create(['name' => 'BetaCo']);

        $response = $this->adminGet('/organizations?search=Alpha');

        $response->assertOk();
        $response->assertSee('AlphaCo');
        $response->assertDontSee('BetaCo');
    }

    public function test_organizations_index_filter_by_status(): void
    {
        Organization::factory()->create(['name' => 'ActiveOrg', 'status' => 'active']);
        Organization::factory()->create(['name' => 'SuspendedOrg', 'status' => 'suspended']);

        $response = $this->adminGet('/organizations?status=suspended');

        $response->assertOk();
        $response->assertSee('SuspendedOrg');
        $response->assertDontSee('ActiveOrg');
    }

    public function test_organizations_show(): void
    {
        $org = Organization::factory()->create(['name' => 'DetailCorp']);
        User::factory()->create(['organization_id' => $org->id, 'name' => 'John Doe']);

        $response = $this->adminGet("/organizations/{$org->id}");

        $response->assertOk();
        $response->assertSee('DetailCorp');
        $response->assertSee('John Doe');
    }

    public function test_organizations_edit_and_update(): void
    {
        $org = Organization::factory()->create(['name' => 'OldName', 'slug' => 'old-name']);

        $response = $this->adminGet("/organizations/{$org->id}/edit");
        $response->assertOk();
        $response->assertSee('OldName');

        $response = $this->adminPut("/organizations/{$org->id}", [
            'name' => 'NewName',
            'slug' => 'new-name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('organizations', ['id' => $org->id, 'name' => 'NewName', 'slug' => 'new-name']);
    }

    public function test_organizations_suspend(): void
    {
        $org = Organization::factory()->create(['status' => 'active']);

        $response = $this->adminPost("/organizations/{$org->id}/suspend");

        $response->assertRedirect();
        $this->assertDatabaseHas('organizations', ['id' => $org->id, 'status' => 'suspended']);
    }

    public function test_organizations_activate(): void
    {
        $org = Organization::factory()->create(['status' => 'suspended']);

        $response = $this->adminPost("/organizations/{$org->id}/activate");

        $response->assertRedirect();
        $this->assertDatabaseHas('organizations', ['id' => $org->id, 'status' => 'active']);
    }

    public function test_organizations_suspend_already_suspended(): void
    {
        $org = Organization::factory()->create(['status' => 'suspended']);

        $response = $this->adminPost("/organizations/{$org->id}/suspend");

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ── Subscriptions ──

    public function test_subscriptions_index(): void
    {
        $org = Organization::factory()->create();
        $plan = Plan::factory()->create(['slug' => 'sub-test']);
        OrganizationSubscription::factory()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->adminGet('/subscriptions');

        $response->assertOk();
        $response->assertSee($org->name);
    }

    public function test_subscriptions_filter_by_status(): void
    {
        $plan = Plan::factory()->create(['slug' => 'sub-filter']);
        $activeOrg = Organization::factory()->create(['name' => 'ActiveSub']);
        $canceledOrg = Organization::factory()->create(['name' => 'CanceledSub']);

        OrganizationSubscription::factory()->create([
            'organization_id' => $activeOrg->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        OrganizationSubscription::factory()->canceled()->create([
            'organization_id' => $canceledOrg->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->adminGet('/subscriptions?status=active');

        $response->assertOk();
        $response->assertSee('ActiveSub');
        $response->assertDontSee('CanceledSub');
    }

    public function test_subscriptions_show(): void
    {
        $plan = Plan::factory()->create(['slug' => 'sub-show']);
        $org = Organization::factory()->create();
        $sub = OrganizationSubscription::factory()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->adminGet("/subscriptions/{$sub->id}");

        $response->assertOk();
        $response->assertSee($org->name);
    }

    public function test_subscriptions_update(): void
    {
        $oldPlan = Plan::factory()->create(['slug' => 'sub-old']);
        $newPlan = Plan::factory()->create(['slug' => 'sub-new']);
        $org = Organization::factory()->create();
        $sub = OrganizationSubscription::factory()->create([
            'organization_id' => $org->id,
            'plan_id' => $oldPlan->id,
        ]);

        $response = $this->adminPut("/subscriptions/{$sub->id}", [
            'plan_id' => $newPlan->id,
            'status' => 'active',
            'billing_cycle' => 'yearly',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('organization_subscriptions', [
            'id' => $sub->id,
            'plan_id' => $newPlan->id,
            'billing_cycle' => 'yearly',
        ]);
    }

    // ── Usage ──

    public function test_usage_index(): void
    {
        $org = Organization::factory()->create(['name' => 'UsageOrg']);
        OrganizationUsageMonthly::create([
            'organization_id' => $org->id,
            'feature_code' => 'max_conversations_monthly',
            'period' => now()->format('Y-m'),
            'usage' => 42,
        ]);

        $response = $this->adminGet('/usage');

        $response->assertOk();
        $response->assertSee('UsageOrg');
        $response->assertSee('42');
    }

    public function test_usage_filter_by_period(): void
    {
        $org = Organization::factory()->create();
        OrganizationUsageMonthly::create([
            'organization_id' => $org->id,
            'feature_code' => 'max_conversations_monthly',
            'period' => '2025-01',
            'usage' => 10,
        ]);

        $response = $this->adminGet('/usage?period=2025-01');

        $response->assertOk();
        $response->assertSee('10');
    }

    // ── Alerts ──

    public function test_alerts_index(): void
    {
        SaasAlert::factory()->create([
            'title' => 'Test Alert',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->adminGet('/alerts');

        $response->assertOk();
        $response->assertSee('Test Alert');
    }

    public function test_alerts_create_form(): void
    {
        $response = $this->adminGet('/alerts/create');
        $response->assertOk();
        $response->assertSee('Create Alert');
    }

    public function test_alerts_store(): void
    {
        $response = $this->adminPost('/alerts', [
            'type' => 'warning',
            'title' => 'New Warning',
            'message' => 'Something needs attention.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('saas_alerts', [
            'type' => 'warning',
            'title' => 'New Warning',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_alerts_store_for_specific_organization(): void
    {
        $org = Organization::factory()->create();

        $response = $this->adminPost('/alerts', [
            'organization_id' => $org->id,
            'type' => 'critical',
            'title' => 'Org Alert',
            'message' => 'Issue with this org.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('saas_alerts', [
            'organization_id' => $org->id,
            'title' => 'Org Alert',
        ]);
    }

    public function test_alerts_edit_and_update(): void
    {
        $alert = SaasAlert::factory()->create([
            'title' => 'Original',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->adminGet("/alerts/{$alert->id}/edit");
        $response->assertOk();

        $response = $this->adminPut("/alerts/{$alert->id}", [
            'type' => 'info',
            'title' => 'Updated',
            'message' => 'Updated message.',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('saas_alerts', ['id' => $alert->id, 'title' => 'Updated']);
    }

    public function test_alerts_resolve(): void
    {
        $alert = SaasAlert::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $response = $this->adminPost("/alerts/{$alert->id}/resolve");

        $response->assertRedirect();
        $alert->refresh();
        $this->assertTrue($alert->isResolved());
        $this->assertFalse($alert->is_active);
        $this->assertEquals($this->admin->id, $alert->resolved_by);
    }

    public function test_alerts_destroy(): void
    {
        $alert = SaasAlert::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $response = $this->adminDelete("/alerts/{$alert->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('saas_alerts', ['id' => $alert->id]);
    }

    public function test_alerts_validation(): void
    {
        $response = $this->adminPost('/alerts', []);

        $response->assertSessionHasErrors(['type', 'title', 'message']);
    }

    // ── SaasAlert model ──

    public function test_alert_is_global(): void
    {
        $global = SaasAlert::factory()->create([
            'organization_id' => null,
            'created_by' => $this->admin->id,
        ]);

        $org = Organization::factory()->create();
        $orgAlert = SaasAlert::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $this->admin->id,
        ]);

        $this->assertTrue($global->isGlobal());
        $this->assertFalse($orgAlert->isGlobal());
    }

    public function test_alert_is_resolved(): void
    {
        $unresolved = SaasAlert::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $resolved = SaasAlert::factory()->resolved()->create([
            'created_by' => $this->admin->id,
            'resolved_by' => $this->admin->id,
        ]);

        $this->assertFalse($unresolved->isResolved());
        $this->assertTrue($resolved->isResolved());
    }

    // ── Maintenance ──

    public function test_maintenance_index(): void
    {
        Organization::factory()->create(['name' => 'MaintOrg']);

        $response = $this->adminGet('/maintenance');

        $response->assertOk();
        $response->assertSee('MaintOrg');
        $response->assertSee('Online');
    }

    public function test_maintenance_toggle_on(): void
    {
        $org = Organization::factory()->create();

        $response = $this->adminPost("/maintenance/{$org->id}/toggle");

        $response->assertRedirect();
        $org->refresh();
        $this->assertTrue($org->settings['maintenance_mode']);
    }

    public function test_maintenance_toggle_off(): void
    {
        $org = Organization::factory()->create([
            'settings' => ['maintenance_mode' => true],
        ]);

        $response = $this->adminPost("/maintenance/{$org->id}/toggle");

        $response->assertRedirect();
        $org->refresh();
        $this->assertFalse($org->settings['maintenance_mode']);
    }

    // ── Tenant middleware respects maintenance mode ──

    public function test_tenant_middleware_blocks_maintenance_mode(): void
    {
        $org = Organization::factory()->create([
            'slug' => 'maint-test',
            'settings' => ['maintenance_mode' => true],
        ]);

        // Simulate what ResolveTenant does
        $middleware = new \App\Http\Middleware\ResolveTenant();
        $request = \Illuminate\Http\Request::create('http://maint-test.' . config('app.base_domain') . '/test');
        $request->headers->set('HOST', 'maint-test.' . config('app.base_domain'));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $middleware->handle($request, fn ($r) => response('ok'));
    }

    public function test_tenant_middleware_allows_non_maintenance(): void
    {
        $org = Organization::factory()->create([
            'slug' => 'normal-test',
            'settings' => ['maintenance_mode' => false],
        ]);

        $middleware = new \App\Http\Middleware\ResolveTenant();
        $request = \Illuminate\Http\Request::create('http://normal-test.' . config('app.base_domain') . '/test');
        $request->headers->set('HOST', 'normal-test.' . config('app.base_domain'));

        $response = $middleware->handle($request, fn ($r) => response('ok'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(app()->bound('tenant'));
    }
}
