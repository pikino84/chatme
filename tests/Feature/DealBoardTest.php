<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DealBoardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Pipeline $pipeline;
    private PipelineStage $stage1;
    private PipelineStage $stage2;
    private User $supervisor;
    private User $agent;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::factory()->create();
        $this->pipeline = Pipeline::factory()->create([
            'organization_id' => $this->org->id,
            'is_default' => true,
        ]);
        $this->stage1 = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'New',
            'position' => 1,
        ]);
        $this->stage2 = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Qualified',
            'position' => 2,
        ]);

        $this->supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $this->supervisor->assignRole('supervisor');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        $this->domain = 'http://app.' . config('app.base_domain');

        app()->instance('tenant', $this->org);
        Event::fake();
    }

    private function dealsUrl(string $path = ''): string
    {
        return "{$this->domain}/deals{$path}";
    }

    public function test_supervisor_can_view_board(): void
    {
        $response = $this->actingAs($this->supervisor)->get($this->dealsUrl());

        $response->assertOk();
        $response->assertViewIs('deals.board');
        $response->assertViewHas('stages');
        $response->assertViewHas('pipelines');
    }

    public function test_agent_sees_only_own_deals(): void
    {
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage1->id,
            'assigned_user_id' => $this->agent->id,
            'contact_name' => 'My Deal',
            'stage_entered_at' => now(),
        ]);

        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage1->id,
            'assigned_user_id' => null,
            'contact_name' => 'Other Deal',
            'stage_entered_at' => now(),
        ]);

        $response = $this->actingAs($this->agent)->get($this->dealsUrl());

        $response->assertOk();
        $stages = $response->viewData('stages');
        $allDeals = $stages->flatMap->deals;
        $this->assertCount(1, $allDeals);
        $this->assertEquals('My Deal', $allDeals->first()->contact_name);
    }

    public function test_supervisor_can_view_deal(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage1->id,
            'contact_name' => 'Test Deal',
            'stage_entered_at' => now(),
        ]);

        $response = $this->actingAs($this->supervisor)->get($this->dealsUrl("/{$deal->id}"));

        $response->assertOk();
        $response->assertViewHas('selectedDeal');
    }

    public function test_supervisor_can_create_deal(): void
    {
        $response = $this->actingAs($this->supervisor)->post($this->dealsUrl(), [
            'pipeline_id' => $this->pipeline->id,
            'contact_name' => 'New Customer',
            'contact_email' => 'new@example.com',
            'value' => 5000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('deals', [
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'contact_name' => 'New Customer',
            'contact_email' => 'new@example.com',
        ]);
    }

    public function test_supervisor_can_move_deal(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage1->id,
            'contact_name' => 'Movable Deal',
            'stage_entered_at' => now(),
        ]);

        $response = $this->actingAs($this->supervisor)->post($this->dealsUrl("/{$deal->id}/move"), [
            'pipeline_stage_id' => $this->stage2->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals($this->stage2->id, $deal->fresh()->pipeline_stage_id);
        $this->assertDatabaseHas('deal_stage_history', [
            'deal_id' => $deal->id,
            'to_stage_id' => $this->stage2->id,
        ]);
    }

    public function test_supervisor_can_add_note(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage1->id,
            'contact_name' => 'Noted Deal',
            'stage_entered_at' => now(),
        ]);

        $response = $this->actingAs($this->supervisor)->post($this->dealsUrl("/{$deal->id}/notes"), [
            'body' => 'Follow up next week',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('deal_notes', [
            'deal_id' => $deal->id,
            'body' => 'Follow up next week',
            'user_id' => $this->supervisor->id,
        ]);
    }

    public function test_cross_tenant_isolation(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherPipeline = Pipeline::factory()->create(['organization_id' => $otherOrg->id]);
        $otherStage = PipelineStage::factory()->create([
            'organization_id' => $otherOrg->id,
            'pipeline_id' => $otherPipeline->id,
        ]);
        $deal = Deal::factory()->create([
            'organization_id' => $otherOrg->id,
            'pipeline_id' => $otherPipeline->id,
            'pipeline_stage_id' => $otherStage->id,
            'contact_name' => 'Other Org Deal',
            'stage_entered_at' => now(),
        ]);

        $response = $this->actingAs($this->supervisor)->get($this->dealsUrl("/{$deal->id}"));

        $response->assertNotFound();
    }
}
