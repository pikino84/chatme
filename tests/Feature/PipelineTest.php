<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
    }

    // --- Model ---

    public function test_pipeline_belongs_to_organization(): void
    {
        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);

        $this->assertEquals($this->org->id, $pipeline->organization_id);
    }

    public function test_pipeline_has_stages_ordered_by_position(): void
    {
        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Second',
            'position' => 2,
        ]);
        PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'First',
            'position' => 1,
        ]);

        $stages = $pipeline->stages;
        $this->assertEquals('First', $stages->first()->name);
        $this->assertEquals('Second', $stages->last()->name);
    }

    public function test_pipeline_first_stage(): void
    {
        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Third',
            'position' => 3,
        ]);
        PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'First',
            'position' => 1,
        ]);

        $this->assertEquals('First', $pipeline->firstStage->name);
    }

    public function test_pipeline_is_default_helper(): void
    {
        $pipeline = Pipeline::factory()->default()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($pipeline->isDefault());
    }

    public function test_pipeline_has_deals(): void
    {
        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $stage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
        ]);
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $this->assertCount(1, $pipeline->deals);
    }

    // --- Stage ---

    public function test_stage_is_terminal(): void
    {
        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $wonStage = PipelineStage::factory()->won()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
        ]);
        $lostStage = PipelineStage::factory()->lost()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
        ]);
        $normalStage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
        ]);

        $this->assertTrue($wonStage->isTerminal());
        $this->assertTrue($lostStage->isTerminal());
        $this->assertFalse($normalStage->isTerminal());
    }

    public function test_stage_max_duration(): void
    {
        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $stage = PipelineStage::factory()->withMaxDuration(48)->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
        ]);

        $this->assertTrue($stage->hasMaxDuration());
        $this->assertEquals(172800, $stage->maxDurationInSeconds());
    }

    public function test_stage_without_max_duration(): void
    {
        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $stage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'max_duration_hours' => null,
        ]);

        $this->assertFalse($stage->hasMaxDuration());
        $this->assertNull($stage->maxDurationInSeconds());
    }

    // --- Tenant Scope ---

    public function test_pipeline_tenant_scope(): void
    {
        Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $otherOrg = Organization::factory()->create();
        Pipeline::factory()->create(['organization_id' => $otherOrg->id]);

        app()->instance('tenant', $this->org);
        $this->assertCount(1, Pipeline::all());
    }

    // --- Policy ---

    public function test_policy_org_admin_can_create_pipeline(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $this->assertTrue($admin->can('create', Pipeline::class));
    }

    public function test_policy_agent_cannot_create_pipeline(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->assertFalse($agent->can('create', Pipeline::class));
    }

    public function test_policy_cannot_delete_pipeline_with_deals(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $stage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
        ]);
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $this->assertFalse($admin->can('delete', $pipeline));
    }

    public function test_policy_can_delete_empty_pipeline(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($admin->can('delete', $pipeline));
    }

    public function test_policy_cross_tenant_cannot_view(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $otherUser->assignRole('org_admin');

        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);

        $this->assertFalse($otherUser->can('view', $pipeline));
    }
}
