<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudTest extends TestCase
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

    // ── Users CRUD ──

    public function test_admin_can_list_users(): void
    {
        User::factory()->create();

        $response = $this->adminGet('/users');
        $response->assertOk();
        $response->assertViewIs('saas-admin.users.index');
    }

    public function test_admin_can_create_user(): void
    {
        $org = Organization::factory()->create();

        $response = $this->adminPost('/users', [
            'name' => 'New Agent',
            'email' => 'agent@example.com',
            'password' => 'Password123!',
            'organization_id' => $org->id,
            'role' => 'agent',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'agent@example.com']);
    }

    public function test_admin_can_view_user(): void
    {
        $user = User::factory()->create();

        $response = $this->adminGet("/users/{$user->id}");
        $response->assertOk();
        $response->assertViewIs('saas-admin.users.show');
    }

    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('agent');

        $response = $this->adminPut("/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'org_admin',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertEquals('Updated Name', $user->fresh()->name);
        $this->assertTrue($user->fresh()->hasRole('org_admin'));
    }

    public function test_admin_can_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->adminDelete("/users/{$user->id}");
        $response->assertRedirect(route('saas-admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $response = $this->adminDelete("/users/{$this->admin->id}");
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_user_search_filter(): void
    {
        User::factory()->create(['name' => 'Alice Wonderland', 'email' => 'alice@example.com']);
        User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.com']);

        $response = $this->adminGet('/users?search=alice');
        $response->assertOk();
        $response->assertSee('Alice Wonderland');
    }

    // ── Plans CRUD ──

    public function test_admin_can_list_plans(): void
    {
        Plan::factory()->create();

        $response = $this->adminGet('/plans');
        $response->assertOk();
        $response->assertViewIs('saas-admin.plans.index');
    }

    public function test_admin_can_create_plan(): void
    {
        $response = $this->adminPost('/plans', [
            'name' => 'Basic',
            'slug' => 'basic',
            'price_monthly' => 9900,
            'price_yearly' => 99900,
            'sort_order' => 1,
            'trial_days' => 7,
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('plans', ['slug' => 'basic']);
    }

    public function test_admin_can_view_plan(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->adminGet("/plans/{$plan->id}");
        $response->assertOk();
        $response->assertViewIs('saas-admin.plans.show');
    }

    public function test_admin_can_update_plan(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->adminPut("/plans/{$plan->id}", [
            'name' => 'Updated Plan',
            'slug' => $plan->slug,
            'price_monthly' => 19900,
            'price_yearly' => 199900,
            'sort_order' => 2,
            'trial_days' => 14,
        ]);

        $response->assertRedirect();
        $this->assertEquals('Updated Plan', $plan->fresh()->name);
    }

    public function test_admin_can_update_plan_with_features(): void
    {
        $plan = Plan::factory()->create();
        PlanFeature::create(['code' => 'max_agents', 'description' => 'Max agents', 'type' => 'limit']);

        $response = $this->adminPut("/plans/{$plan->id}", [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'sort_order' => $plan->sort_order,
            'trial_days' => $plan->trial_days,
            'features' => ['max_agents' => '10'],
        ]);

        $response->assertRedirect();
        $this->assertEquals('10', $plan->fresh()->getFeatureValue('max_agents'));
    }

    public function test_admin_can_delete_plan_without_subscriptions(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->adminDelete("/plans/{$plan->id}");
        $response->assertRedirect(route('saas-admin.plans.index'));
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_admin_cannot_delete_plan_with_subscriptions(): void
    {
        $plan = Plan::factory()->create();
        $org = Organization::factory()->create();
        OrganizationSubscription::factory()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->adminDelete("/plans/{$plan->id}");
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    // ── Organization create/destroy ──

    public function test_admin_can_create_organization(): void
    {
        $response = $this->adminPost('/organizations', [
            'name' => 'Acme Corp',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('organizations', ['name' => 'Acme Corp', 'slug' => 'acme-corp']);
    }

    public function test_admin_can_delete_empty_organization(): void
    {
        $org = Organization::factory()->create();

        $response = $this->adminDelete("/organizations/{$org->id}");
        $response->assertRedirect(route('saas-admin.organizations.index'));
        $this->assertDatabaseMissing('organizations', ['id' => $org->id]);
    }

    public function test_admin_cannot_delete_organization_with_users(): void
    {
        $org = Organization::factory()->create();
        User::factory()->create(['organization_id' => $org->id]);

        $response = $this->adminDelete("/organizations/{$org->id}");
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('organizations', ['id' => $org->id]);
    }

    public function test_org_slug_cannot_change_when_has_users(): void
    {
        $org = Organization::factory()->create(['slug' => 'original-slug']);
        User::factory()->create(['organization_id' => $org->id]);

        $response = $this->adminPut("/organizations/{$org->id}", [
            'name' => 'New Name',
            'slug' => 'new-slug',
        ]);

        $response->assertRedirect();
        $this->assertEquals('original-slug', $org->fresh()->slug);
        $this->assertEquals('New Name', $org->fresh()->name);
    }

    public function test_org_slug_can_change_when_no_dependencies(): void
    {
        $org = Organization::factory()->create(['slug' => 'old-slug']);

        $response = $this->adminPut("/organizations/{$org->id}", [
            'name' => 'Updated Org',
            'slug' => 'new-slug',
        ]);

        $response->assertRedirect();
        $this->assertEquals('new-slug', $org->fresh()->slug);
    }

    // ── Subscription create ──

    public function test_admin_can_create_subscription(): void
    {
        $org = Organization::factory()->create();
        $plan = Plan::factory()->create();

        $response = $this->adminPost('/subscriptions', [
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('organization_subscriptions', [
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
        ]);
    }

    // ── Validation ──

    public function test_user_create_validates_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->adminPost('/users', [
            'name' => 'Dupe',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'role' => 'agent',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_org_create_auto_generates_unique_slug(): void
    {
        Organization::factory()->create(['slug' => 'acme-corp']);

        $response = $this->adminPost('/organizations', [
            'name' => 'Acme Corp',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('organizations', ['slug' => 'acme-corp-1']);
    }

    public function test_plan_create_validates_unique_slug(): void
    {
        Plan::factory()->create(['slug' => 'taken']);

        $response = $this->adminPost('/plans', [
            'name' => 'Dupe',
            'slug' => 'taken',
            'price_monthly' => 100,
            'price_yearly' => 1000,
            'sort_order' => 1,
            'trial_days' => 0,
        ]);

        $response->assertSessionHasErrors('slug');
    }
}
