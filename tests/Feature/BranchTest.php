<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_branch_belongs_to_organization(): void
    {
        $org = Organization::factory()->create();
        $branch = Branch::factory()->create(['organization_id' => $org->id]);

        $this->assertEquals($org->id, $branch->organization->id);
        $this->assertTrue($org->branches->contains($branch));
    }

    public function test_branch_is_deleted_when_organization_is_deleted(): void
    {
        $org = Organization::factory()->create();
        $branch = Branch::factory()->create(['organization_id' => $org->id]);

        $org->delete();

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_user_with_permission_can_view_branch_of_own_organization(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole('org_admin');
        $branch = Branch::factory()->create(['organization_id' => $org->id]);

        $this->assertTrue($user->can('view', $branch));
    }

    public function test_user_cannot_view_branch_of_other_organization(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org1->id]);
        $user->assignRole('org_admin');
        $branch = Branch::factory()->create(['organization_id' => $org2->id]);

        $this->assertFalse($user->can('view', $branch));
    }

    public function test_branch_can_be_deactivated(): void
    {
        $branch = Branch::factory()->inactive()->create();

        $this->assertFalse($branch->is_active);
    }

    public function test_multiple_branches_per_organization(): void
    {
        $org = Organization::factory()->create();
        Branch::factory()->count(3)->create(['organization_id' => $org->id]);

        $this->assertCount(3, $org->branches);
    }
}
