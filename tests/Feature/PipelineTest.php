<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $admin;
    protected User $agent;
    protected Pipeline $pipeline;

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

        $this->pipeline = Pipeline::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Ventas',
            'is_default' => true,
        ]);

        PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Nuevo',
            'position' => 1,
            'color' => '#3B82F6',
        ]);
    }

    // ── Index ──

    public function test_admin_can_view_pipelines_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pipelines.index'));

        $response->assertOk();
        $response->assertSee('Ventas');
        $response->assertSee('Nuevo');
    }

    public function test_agent_can_view_pipelines_index(): void
    {
        $response = $this->actingAs($this->agent)->get(route('pipelines.index'));

        $response->assertOk();
    }

    public function test_unauthenticated_cannot_access_pipelines(): void
    {
        $response = $this->get(route('pipelines.index'));

        $response->assertRedirect();
    }

    // ── Create ──

    public function test_admin_can_view_create_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pipelines.create'));

        $response->assertOk();
        $response->assertSee('Nuevo Pipeline');
    }

    public function test_agent_cannot_view_create_form(): void
    {
        $response = $this->actingAs($this->agent)->get(route('pipelines.create'));

        $response->assertForbidden();
    }

    // ── Store ──

    public function test_admin_can_create_pipeline_with_stages(): void
    {
        $response = $this->actingAs($this->admin)->post(route('pipelines.store'), [
            'name' => 'Postventa',
            'stages' => [
                ['name' => 'Inicio', 'color' => '#10B981', 'max_duration_hours' => 24],
                ['name' => 'Seguimiento', 'color' => '#F59E0B'],
                ['name' => 'Cerrado', 'color' => '#22C55E', 'is_won' => true],
            ],
        ]);

        $response->assertRedirect(route('pipelines.index'));
        $this->assertDatabaseHas('pipelines', [
            'organization_id' => $this->org->id,
            'name' => 'Postventa',
        ]);

        $pipeline = Pipeline::where('name', 'Postventa')->first();
        $this->assertEquals(3, $pipeline->stages()->count());
        $this->assertEquals('Inicio', $pipeline->stages()->orderBy('position')->first()->name);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->actingAs($this->admin)->post(route('pipelines.store'), [
            'stages' => [['name' => 'Test']],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_requires_at_least_one_stage(): void
    {
        $response = $this->actingAs($this->admin)->post(route('pipelines.store'), [
            'name' => 'Empty Pipeline',
            'stages' => [],
        ]);

        $response->assertSessionHasErrors('stages');
    }

    public function test_store_rejects_multiple_won_stages(): void
    {
        $response = $this->actingAs($this->admin)->post(route('pipelines.store'), [
            'name' => 'Doble Ganado',
            'stages' => [
                ['name' => 'Inicio', 'color' => '#3B82F6'],
                ['name' => 'Ganado A', 'color' => '#10B981', 'is_won' => true],
                ['name' => 'Ganado B', 'color' => '#22C55E', 'is_won' => true],
            ],
        ]);

        $response->assertSessionHasErrors('stages');
        $this->assertDatabaseMissing('pipelines', ['name' => 'Doble Ganado']);
    }

    public function test_store_rejects_multiple_lost_stages(): void
    {
        $response = $this->actingAs($this->admin)->post(route('pipelines.store'), [
            'name' => 'Doble Perdido',
            'stages' => [
                ['name' => 'Inicio', 'color' => '#3B82F6'],
                ['name' => 'Perdido A', 'color' => '#EF4444', 'is_lost' => true],
                ['name' => 'Perdido B', 'color' => '#DC2626', 'is_lost' => true],
            ],
        ]);

        $response->assertSessionHasErrors('stages');
        $this->assertDatabaseMissing('pipelines', ['name' => 'Doble Perdido']);
    }

    public function test_store_rejects_stage_marked_won_and_lost(): void
    {
        $response = $this->actingAs($this->admin)->post(route('pipelines.store'), [
            'name' => 'Ambos',
            'stages' => [
                ['name' => 'Confuso', 'color' => '#10B981', 'is_won' => true, 'is_lost' => true],
            ],
        ]);

        $response->assertSessionHasErrors('stages');
        $this->assertDatabaseMissing('pipelines', ['name' => 'Ambos']);
    }

    public function test_store_allows_one_won_and_one_lost(): void
    {
        $response = $this->actingAs($this->admin)->post(route('pipelines.store'), [
            'name' => 'Bien Formado',
            'stages' => [
                ['name' => 'Inicio', 'color' => '#3B82F6'],
                ['name' => 'Ganado', 'color' => '#10B981', 'is_won' => true],
                ['name' => 'Perdido', 'color' => '#EF4444', 'is_lost' => true],
            ],
        ]);

        $response->assertRedirect(route('pipelines.index'));
        $this->assertDatabaseHas('pipelines', ['name' => 'Bien Formado']);
    }

    public function test_update_rejects_multiple_won_stages(): void
    {
        $stage = $this->pipeline->stages()->first();

        $response = $this->actingAs($this->admin)->put(route('pipelines.update', $this->pipeline), [
            'name' => 'Ventas',
            'stages' => [
                ['id' => $stage->id, 'name' => 'Nuevo', 'color' => '#3B82F6'],
                ['name' => 'Ganado A', 'color' => '#10B981', 'is_won' => true],
                ['name' => 'Ganado B', 'color' => '#22C55E', 'is_won' => true],
            ],
        ]);

        $response->assertSessionHasErrors('stages');
    }

    public function test_agent_cannot_create_pipeline(): void
    {
        $response = $this->actingAs($this->agent)->post(route('pipelines.store'), [
            'name' => 'Test',
            'stages' => [['name' => 'Stage']],
        ]);

        $response->assertForbidden();
    }

    // ── Edit ──

    public function test_admin_can_view_edit_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pipelines.edit', $this->pipeline));

        $response->assertOk();
        $response->assertSee('Editar Pipeline');
        $response->assertSee('Ventas');
    }

    // ── Update ──

    public function test_admin_can_update_pipeline(): void
    {
        $stage = $this->pipeline->stages()->first();

        $response = $this->actingAs($this->admin)->put(route('pipelines.update', $this->pipeline), [
            'name' => 'Ventas Actualizado',
            'stages' => [
                ['id' => $stage->id, 'name' => 'Prospecto', 'color' => '#3B82F6'],
                ['name' => 'Calificado', 'color' => '#F59E0B'],
            ],
        ]);

        $response->assertRedirect(route('pipelines.index'));
        $this->assertDatabaseHas('pipelines', ['id' => $this->pipeline->id, 'name' => 'Ventas Actualizado']);
        $this->assertEquals(2, $this->pipeline->fresh()->stages()->count());
    }

    public function test_update_preserves_stages_with_deals(): void
    {
        $stage = $this->pipeline->stages()->first();

        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('pipelines.update', $this->pipeline), [
            'name' => 'Ventas',
            'stages' => [
                ['name' => 'Nueva Etapa', 'color' => '#10B981'],
            ],
        ]);

        $response->assertRedirect(route('pipelines.index'));
        $this->assertDatabaseHas('pipeline_stages', ['id' => $stage->id]);
    }

    // ── Delete ──

    public function test_admin_can_delete_empty_pipeline(): void
    {
        $pipeline = Pipeline::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Temporal',
            'is_default' => false,
        ]);
        PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('pipelines.destroy', $pipeline));

        $response->assertRedirect(route('pipelines.index'));
        $this->assertDatabaseMissing('pipelines', ['id' => $pipeline->id]);
    }

    public function test_cannot_delete_pipeline_with_deals(): void
    {
        $stage = $this->pipeline->stages()->first();

        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('pipelines.destroy', $this->pipeline));

        // PipelinePolicy::delete returns false when pipeline has deals
        $response->assertForbidden();
        $this->assertDatabaseHas('pipelines', ['id' => $this->pipeline->id]);
    }

    // ── Set Default ──

    public function test_admin_can_set_default_pipeline(): void
    {
        $other = Pipeline::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Otro Pipeline',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->admin)->post(route('pipelines.set-default', $other));

        $response->assertRedirect();
        $this->assertTrue($other->fresh()->is_default);
        $this->assertFalse($this->pipeline->fresh()->is_default);
    }

    // ── Tenant Isolation ──

    public function test_tenant_isolation_on_index(): void
    {
        $otherOrg = Organization::factory()->create();
        Pipeline::factory()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Pipeline Ajeno',
        ]);

        $response = $this->actingAs($this->admin)->get(route('pipelines.index'));

        $response->assertOk();
        $response->assertDontSee('Pipeline Ajeno');
    }

    public function test_cannot_edit_other_org_pipeline(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherPipeline = Pipeline::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('pipelines.edit', $otherPipeline));

        // Global scope filters out the pipeline, so it returns 404
        $response->assertNotFound();
    }
}
