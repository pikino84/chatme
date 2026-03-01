<?php

namespace Tests\Feature;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbCategoryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
    }

    // --- Model Relations ---

    public function test_category_belongs_to_organization(): void
    {
        $category = KbCategory::factory()->create(['organization_id' => $this->org->id]);

        $this->assertEquals($this->org->id, $category->organization_id);
    }

    public function test_category_has_articles(): void
    {
        $category = KbCategory::factory()->create(['organization_id' => $this->org->id]);
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'kb_category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $this->assertCount(1, $category->articles);
    }

    public function test_category_has_parent(): void
    {
        $parent = KbCategory::factory()->create(['organization_id' => $this->org->id]);
        $child = KbCategory::factory()->withParent($parent)->create();

        $this->assertEquals($parent->id, $child->parent->id);
    }

    public function test_category_has_children(): void
    {
        $parent = KbCategory::factory()->create(['organization_id' => $this->org->id]);
        KbCategory::factory()->withParent($parent)->create();
        KbCategory::factory()->withParent($parent)->create();

        $this->assertCount(2, $parent->children);
    }

    public function test_category_is_active_helper(): void
    {
        $active = KbCategory::factory()->create(['organization_id' => $this->org->id]);
        $inactive = KbCategory::factory()->inactive()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($inactive->isActive());
    }

    // --- Tenant Scope ---

    public function test_category_tenant_scope(): void
    {
        $otherOrg = Organization::factory()->create();

        KbCategory::factory()->create(['organization_id' => $this->org->id]);
        KbCategory::factory()->create(['organization_id' => $otherOrg->id]);

        app()->instance('tenant', $this->org);
        $this->assertCount(1, KbCategory::all());
    }

    // --- Policy ---

    public function test_policy_org_admin_can_create_category(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $this->assertTrue($admin->can('create', KbCategory::class));
    }

    public function test_policy_agent_cannot_create_category(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->assertFalse($agent->can('create', KbCategory::class));
    }

    public function test_policy_cannot_delete_category_with_articles(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $category = KbCategory::factory()->create(['organization_id' => $this->org->id]);
        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'kb_category_id' => $category->id,
            'created_by' => $admin->id,
        ]);

        $this->assertFalse($admin->can('delete', $category));
    }

    public function test_policy_can_delete_empty_category(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $category = KbCategory::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($admin->can('delete', $category));
    }

    public function test_policy_cross_tenant_cannot_view(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $otherUser->assignRole('org_admin');

        $category = KbCategory::factory()->create(['organization_id' => $this->org->id]);

        $this->assertFalse($otherUser->can('view', $category));
    }
}
