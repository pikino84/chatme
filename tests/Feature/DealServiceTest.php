<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\DealService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DealServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Pipeline $pipeline;
    private PipelineStage $firstStage;
    private PipelineStage $secondStage;
    private PipelineStage $wonStage;
    private PipelineStage $lostStage;
    private DealService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->pipeline = Pipeline::factory()->default()->create(['organization_id' => $this->org->id]);
        $this->firstStage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'New',
            'position' => 1,
        ]);
        $this->secondStage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Qualified',
            'position' => 2,
        ]);
        $this->wonStage = PipelineStage::factory()->won()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'position' => 10,
        ]);
        $this->lostStage = PipelineStage::factory()->lost()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'position' => 11,
        ]);
        $this->service = new DealService();
    }

    // --- convertToDeal ---

    public function test_convert_to_deal_creates_deal_from_conversation(): void
    {
        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $channel->id,
            'contact_name' => 'Carlos Lopez',
            'contact_identifier' => '+5215512345678',
        ]);

        $deal = $this->service->convertToDeal($conv);

        $this->assertEquals($this->org->id, $deal->organization_id);
        $this->assertEquals($this->pipeline->id, $deal->pipeline_id);
        $this->assertEquals($this->firstStage->id, $deal->pipeline_stage_id);
        $this->assertEquals($conv->id, $deal->conversation_id);
        $this->assertEquals('Carlos Lopez', $deal->contact_name);
        $this->assertEquals('+5215512345678', $deal->contact_phone);
        $this->assertEquals('open', $deal->status);
        $this->assertCount(1, $deal->stageHistory);
    }

    public function test_convert_to_deal_with_overrides(): void
    {
        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $channel->id,
        ]);

        $deal = $this->service->convertToDeal($conv, null, [
            'value' => 50000,
            'contact_email' => 'test@example.com',
        ]);

        $this->assertEquals('50000.00', $deal->value);
        $this->assertEquals('test@example.com', $deal->contact_email);
    }

    public function test_convert_to_deal_uses_assigned_user(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $channel->id,
            'assigned_user_id' => $user->id,
        ]);

        $deal = $this->service->convertToDeal($conv);

        $this->assertEquals($user->id, $deal->assigned_user_id);
    }

    // --- createDeal ---

    public function test_create_deal_standalone(): void
    {
        $deal = $this->service->createDeal([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'contact_name' => 'Maria Garcia',
            'contact_email' => 'maria@example.com',
            'value' => 25000,
        ]);

        $this->assertEquals('Maria Garcia', $deal->contact_name);
        $this->assertEquals($this->firstStage->id, $deal->pipeline_stage_id);
        $this->assertEquals('open', $deal->status);
        $this->assertCount(1, $deal->stageHistory);
    }

    public function test_create_deal_validates_pipeline_org_match(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherPipeline = Pipeline::factory()->create(['organization_id' => $otherOrg->id]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->createDeal([
            'organization_id' => $this->org->id,
            'pipeline_id' => $otherPipeline->id,
            'contact_name' => 'Test',
        ]);
    }

    // --- moveToStage ---

    public function test_move_to_stage_updates_deal(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->firstStage->id,
        ]);

        $updated = $this->service->moveToStage($deal, $this->secondStage);

        $this->assertEquals($this->secondStage->id, $updated->pipeline_stage_id);
        $this->assertEquals('open', $updated->status);
        $this->assertNull($updated->closed_at);
    }

    public function test_move_to_won_stage_closes_deal(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->firstStage->id,
        ]);

        $updated = $this->service->moveToStage($deal, $this->wonStage);

        $this->assertEquals('won', $updated->status);
        $this->assertNotNull($updated->closed_at);
    }

    public function test_move_to_lost_stage_closes_deal(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->firstStage->id,
        ]);

        $updated = $this->service->moveToStage($deal, $this->lostStage);

        $this->assertEquals('lost', $updated->status);
        $this->assertNotNull($updated->closed_at);
    }

    public function test_move_to_stage_records_history(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->firstStage->id,
        ]);

        $this->service->moveToStage($deal, $this->secondStage, $user);

        $history = $deal->stageHistory()->latest('id')->first();
        $this->assertEquals($this->firstStage->id, $history->from_stage_id);
        $this->assertEquals($this->secondStage->id, $history->to_stage_id);
        $this->assertEquals($user->id, $history->changed_by);
    }

    public function test_move_to_stage_cross_pipeline(): void
    {
        $otherPipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $otherStage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $otherPipeline->id,
            'position' => 1,
        ]);

        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->firstStage->id,
        ]);

        $updated = $this->service->moveToStage($deal, $otherStage);

        $this->assertEquals($otherPipeline->id, $updated->pipeline_id);
        $this->assertEquals($otherStage->id, $updated->pipeline_stage_id);
    }

    // --- setDefaultPipeline ---

    public function test_set_default_pipeline(): void
    {
        $newPipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($this->pipeline->fresh()->isDefault());
        $this->assertFalse($newPipeline->fresh()->isDefault());

        $this->service->setDefaultPipeline($newPipeline);

        $this->assertFalse($this->pipeline->fresh()->isDefault());
        $this->assertTrue($newPipeline->fresh()->isDefault());
    }

    // --- addNote ---

    public function test_add_note_to_deal(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->firstStage->id,
        ]);

        $note = $this->service->addNote($deal, $user, 'Important follow-up required.');

        $this->assertEquals($deal->id, $note->deal_id);
        $this->assertEquals($user->id, $note->user_id);
        $this->assertEquals('Important follow-up required.', $note->body);
        $this->assertEquals($this->org->id, $note->organization_id);
    }

    // --- addAttachment ---

    public function test_add_attachment_to_deal(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->firstStage->id,
        ]);
        $file = UploadedFile::fake()->create('proposal.pdf', 1024, 'application/pdf');

        $attachment = $this->service->addAttachment($deal, $user, $file);

        $this->assertEquals('proposal.pdf', $attachment->file_name);
        $this->assertEquals('application/pdf', $attachment->mime_type);
        $this->assertEquals($this->org->id, $attachment->organization_id);
        Storage::disk('local')->assertExists($attachment->file_path);
    }
}
