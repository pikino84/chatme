<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org1;
    private Organization $org2;
    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org1 = Organization::factory()->create(['slug' => 'tenant-a']);
        $this->org2 = Organization::factory()->create(['slug' => 'tenant-b']);
        $this->user1 = User::factory()->create(['organization_id' => $this->org1->id]);
        $this->user1->assignRole('org_admin');
        $this->user2 = User::factory()->create(['organization_id' => $this->org2->id]);
        $this->user2->assignRole('org_admin');
    }

    public function test_user_cannot_access_other_tenant_branch_via_policy(): void
    {
        $branch = Branch::factory()->create(['organization_id' => $this->org2->id]);

        $this->assertFalse($this->user1->can('view', $branch));
        $this->assertFalse($this->user1->can('update', $branch));
        $this->assertFalse($this->user1->can('delete', $branch));
    }

    public function test_user_can_access_own_tenant_branch_via_policy(): void
    {
        $branch = Branch::factory()->create(['organization_id' => $this->org1->id]);

        $this->assertTrue($this->user1->can('view', $branch));
        $this->assertTrue($this->user1->can('update', $branch));
        $this->assertTrue($this->user1->can('delete', $branch));
    }

    public function test_scope_prevents_cross_tenant_queries(): void
    {
        Branch::factory()->count(2)->create(['organization_id' => $this->org1->id]);
        Branch::factory()->count(3)->create(['organization_id' => $this->org2->id]);

        app()->instance('tenant', $this->org1);
        $this->assertCount(2, Branch::all());

        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->org2);
        $this->assertCount(3, Branch::all());
    }

    public function test_creating_model_auto_assigns_tenant(): void
    {
        app()->instance('tenant', $this->org1);

        $branch = Branch::create(['name' => 'Auto Branch']);

        $this->assertEquals($this->org1->id, $branch->organization_id);
    }

    public function test_organization_policy_prevents_cross_tenant_view(): void
    {
        $this->assertFalse($this->user1->can('view', $this->org2));
        $this->assertFalse($this->user2->can('view', $this->org1));
    }

    public function test_full_isolation_flow(): void
    {
        app()->instance('tenant', $this->org1);
        Branch::factory()->count(2)->create(['organization_id' => $this->org1->id]);

        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->org2);
        Branch::factory()->count(3)->create(['organization_id' => $this->org2->id]);

        $this->assertCount(3, Branch::all());

        app()->forgetInstance('tenant');
        app()->instance('tenant', $this->org1);
        $this->assertCount(2, Branch::all());

        app()->forgetInstance('tenant');
        $this->assertCount(5, Branch::all());
    }
}
