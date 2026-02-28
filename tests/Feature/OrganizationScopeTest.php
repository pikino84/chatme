<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_filters_by_tenant(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        Branch::factory()->count(2)->create(['organization_id' => $org1->id]);
        Branch::factory()->count(3)->create(['organization_id' => $org2->id]);

        app()->instance('tenant', $org1);

        $this->assertCount(2, Branch::all());
    }

    public function test_scope_does_not_filter_without_tenant(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        Branch::factory()->count(2)->create(['organization_id' => $org1->id]);
        Branch::factory()->count(3)->create(['organization_id' => $org2->id]);

        $this->assertCount(5, Branch::all());
    }

    public function test_auto_assigns_organization_id_on_create(): void
    {
        $org = Organization::factory()->create();
        app()->instance('tenant', $org);

        $branch = Branch::create([
            'name' => 'HQ',
        ]);

        $this->assertEquals($org->id, $branch->organization_id);
    }

    public function test_does_not_override_explicit_organization_id(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        app()->instance('tenant', $org1);

        $branch = Branch::create([
            'name' => 'Remote',
            'organization_id' => $org2->id,
        ]);

        $this->assertEquals($org2->id, $branch->organization_id);
    }

    public function test_cannot_query_across_tenants(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $branch = Branch::factory()->create(['organization_id' => $org2->id]);

        app()->instance('tenant', $org1);

        $this->assertNull(Branch::find($branch->id));
    }
}
