<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DealAttachment;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DealAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $admin;
    protected User $agent;
    protected Pipeline $pipeline;
    protected PipelineStage $stage;
    protected Deal $deal;

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

        $this->pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id, 'is_default' => true]);
        $this->stage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
        ]);
        $this->deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
    }

    // ── Upload ──

    public function test_admin_can_upload_attachment(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('deals.attachments.store', $this->deal), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('deal_attachments', [
            'deal_id' => $this->deal->id,
            'user_id' => $this->admin->id,
            'file_name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_upload_requires_file(): void
    {
        $response = $this->actingAs($this->admin)->post(route('deals.attachments.store', $this->deal), []);

        $response->assertSessionHasErrors('file');
    }

    public function test_upload_rejects_file_too_large(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('huge.zip', 11000, 'application/zip'); // 11MB > 10MB limit

        $response = $this->actingAs($this->admin)->post(route('deals.attachments.store', $this->deal), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_agent_with_deal_permission_can_upload(): void
    {
        Storage::fake('local');

        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'assigned_user_id' => $this->agent->id,
        ]);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->agent)->post(route('deals.attachments.store', $deal), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('deal_attachments', ['deal_id' => $deal->id]);
    }

    // ── Delete ──

    public function test_admin_can_delete_attachment(): void
    {
        Storage::fake('local');

        $attachment = DealAttachment::create([
            'organization_id' => $this->org->id,
            'deal_id' => $this->deal->id,
            'user_id' => $this->admin->id,
            'file_name' => 'test.pdf',
            'file_path' => 'deal-attachments/test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('deals.attachments.destroy', [$this->deal, $attachment]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('deal_attachments', ['id' => $attachment->id]);
    }

    public function test_cannot_delete_attachment_from_other_deal(): void
    {
        $otherDeal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $attachment = DealAttachment::create([
            'organization_id' => $this->org->id,
            'deal_id' => $otherDeal->id,
            'user_id' => $this->admin->id,
            'file_name' => 'test.pdf',
            'file_path' => 'deal-attachments/test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('deals.attachments.destroy', [$this->deal, $attachment]));

        $response->assertNotFound();
    }

    // ── Deal Drawer Integration ──

    public function test_deal_show_includes_attachments(): void
    {
        DealAttachment::create([
            'organization_id' => $this->org->id,
            'deal_id' => $this->deal->id,
            'user_id' => $this->admin->id,
            'file_name' => 'propuesta.pdf',
            'file_path' => 'deal-attachments/propuesta.pdf',
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->admin)->get(route('deals.show', $this->deal));

        $response->assertOk();
        $response->assertSee('propuesta.pdf');
        $response->assertSee('Archivos Adjuntos');
    }

    // ── Tenant Isolation ──

    public function test_cannot_upload_to_other_org_deal(): void
    {
        Storage::fake('local');

        $otherOrg = Organization::factory()->create();
        $otherPipeline = Pipeline::factory()->create(['organization_id' => $otherOrg->id]);
        $otherStage = PipelineStage::factory()->create([
            'organization_id' => $otherOrg->id,
            'pipeline_id' => $otherPipeline->id,
        ]);
        $otherDeal = Deal::factory()->create([
            'organization_id' => $otherOrg->id,
            'pipeline_id' => $otherPipeline->id,
            'pipeline_stage_id' => $otherStage->id,
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 100);

        $response = $this->actingAs($this->admin)->post(route('deals.attachments.store', $otherDeal), [
            'file' => $file,
        ]);

        // Global scope filters out the deal, so it returns 404
        $response->assertNotFound();
    }
}
