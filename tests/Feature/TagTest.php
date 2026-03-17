<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tag;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $admin;
    protected User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansAndFeaturesSeeder::class);

        $this->org = Organization::factory()->create();
        app()->instance('tenant', $this->org);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('org_admin');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        $plan = \App\Models\Plan::where('slug', 'enterprise')->first();
        (new BillingService())->subscribe($this->org, $plan);
    }

    // ── Index ──

    public function test_admin_can_view_tags_index(): void
    {
        Tag::create(['organization_id' => $this->org->id, 'name' => 'VIP', 'color' => '#EF4444']);

        $response = $this->actingAs($this->admin)->get(route('tags.index'));

        $response->assertOk();
        $response->assertSee('VIP');
    }

    public function test_agent_can_view_tags_index(): void
    {
        $response = $this->actingAs($this->agent)->get(route('tags.index'));

        $response->assertOk();
    }

    // ── Store ──

    public function test_admin_can_create_tag(): void
    {
        $response = $this->actingAs($this->admin)->post(route('tags.store'), [
            'name' => 'Urgente',
            'color' => '#EF4444',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tags', [
            'organization_id' => $this->org->id,
            'name' => 'Urgente',
            'color' => '#EF4444',
        ]);
    }

    public function test_agent_cannot_create_tag(): void
    {
        $response = $this->actingAs($this->agent)->post(route('tags.store'), [
            'name' => 'Test',
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_create_duplicate_tag(): void
    {
        Tag::create(['organization_id' => $this->org->id, 'name' => 'VIP', 'color' => '#EF4444']);

        $response = $this->actingAs($this->admin)->post(route('tags.store'), [
            'name' => 'VIP',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_store_requires_name(): void
    {
        $response = $this->actingAs($this->admin)->post(route('tags.store'), [
            'color' => '#EF4444',
        ]);

        $response->assertSessionHasErrors('name');
    }

    // ── Update ──

    public function test_admin_can_update_tag(): void
    {
        $tag = Tag::create(['organization_id' => $this->org->id, 'name' => 'VIP', 'color' => '#EF4444']);

        $response = $this->actingAs($this->admin)->put(route('tags.update', $tag), [
            'name' => 'VIP Premium',
            'color' => '#8B5CF6',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'VIP Premium', 'color' => '#8B5CF6']);
    }

    public function test_cannot_update_to_duplicate_name(): void
    {
        Tag::create(['organization_id' => $this->org->id, 'name' => 'VIP', 'color' => '#EF4444']);
        $tag2 = Tag::create(['organization_id' => $this->org->id, 'name' => 'Normal', 'color' => '#6B7280']);

        $response = $this->actingAs($this->admin)->put(route('tags.update', $tag2), [
            'name' => 'VIP',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ── Delete ──

    public function test_admin_can_delete_tag(): void
    {
        $tag = Tag::create(['organization_id' => $this->org->id, 'name' => 'Temporal', 'color' => '#6B7280']);

        $response = $this->actingAs($this->admin)->delete(route('tags.destroy', $tag));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_delete_detaches_deals(): void
    {
        $tag = Tag::create(['organization_id' => $this->org->id, 'name' => 'Temporal', 'color' => '#6B7280']);

        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $stage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
        ]);
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);
        $deal->tags()->attach($tag->id);

        $response = $this->actingAs($this->admin)->delete(route('tags.destroy', $tag));

        $response->assertRedirect();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseMissing('deal_tag', ['tag_id' => $tag->id]);
        // Deal should still exist
        $this->assertDatabaseHas('deals', ['id' => $deal->id]);
    }

    // ── Tag Sync on Deals ──

    public function test_admin_can_sync_tags_on_deal(): void
    {
        $tag1 = Tag::create(['organization_id' => $this->org->id, 'name' => 'VIP', 'color' => '#EF4444']);
        $tag2 = Tag::create(['organization_id' => $this->org->id, 'name' => 'Hot', 'color' => '#F59E0B']);

        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $stage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
        ]);
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('deals.tags.sync', $deal), [
            'tag_ids' => [$tag1->id, $tag2->id],
        ]);

        $response->assertRedirect();
        $this->assertEquals(2, $deal->fresh()->tags()->count());
    }

    public function test_sync_tags_can_remove_all(): void
    {
        $tag = Tag::create(['organization_id' => $this->org->id, 'name' => 'VIP', 'color' => '#EF4444']);

        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $stage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
        ]);
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);
        $deal->tags()->attach($tag->id);

        $response = $this->actingAs($this->admin)->post(route('deals.tags.sync', $deal), [
            'tag_ids' => [],
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, $deal->fresh()->tags()->count());
    }

    // ── Tenant Isolation ──

    public function test_tenant_isolation_tags_not_visible(): void
    {
        $otherOrg = Organization::factory()->create();
        Tag::create(['organization_id' => $otherOrg->id, 'name' => 'Ajena', 'color' => '#000000']);

        $response = $this->actingAs($this->admin)->get(route('tags.index'));

        $response->assertOk();
        $response->assertDontSee('Ajena');
    }
}
