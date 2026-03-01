<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Channel;
use App\Models\Deal;
use App\Models\DealAttachment;
use App\Models\DealCommission;
use App\Models\DealNote;
use App\Models\DealStageHistory;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Pipeline $pipeline;
    private PipelineStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->pipeline = Pipeline::factory()->create([
            'organization_id' => $this->org->id,
            'is_default' => true,
        ]);
        $this->stage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'position' => 1,
        ]);
    }

    // --- Model Relations ---

    public function test_deal_belongs_to_organization(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $this->assertEquals($this->org->id, $deal->organization_id);
    }

    public function test_deal_belongs_to_pipeline(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $this->assertEquals($this->pipeline->id, $deal->pipeline->id);
    }

    public function test_deal_belongs_to_stage(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $this->assertEquals($this->stage->id, $deal->stage->id);
    }

    public function test_deal_can_have_conversation(): void
    {
        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $channel->id,
        ]);
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'conversation_id' => $conv->id,
        ]);

        $this->assertEquals($conv->id, $deal->conversation->id);
    }

    public function test_deal_can_exist_without_conversation(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'conversation_id' => null,
        ]);

        $this->assertNull($deal->conversation);
    }

    public function test_deal_can_be_assigned_to_user(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'assigned_user_id' => $user->id,
        ]);

        $this->assertTrue($deal->isAssignedTo($user));
        $this->assertEquals($user->id, $deal->assignedUser->id);
    }

    public function test_deal_has_notes(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        DealNote::factory()->create([
            'organization_id' => $this->org->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
        ]);

        $this->assertCount(1, $deal->notes);
    }

    public function test_deal_has_attachments(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        DealAttachment::factory()->create([
            'organization_id' => $this->org->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
        ]);

        $this->assertCount(1, $deal->attachments);
    }

    public function test_deal_has_commissions(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        DealCommission::factory()->create([
            'organization_id' => $this->org->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
        ]);

        $this->assertCount(1, $deal->commissions);
    }

    public function test_deal_has_tags(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
        $tag = Tag::factory()->create(['organization_id' => $this->org->id]);
        $deal->tags()->attach($tag);

        $this->assertCount(1, $deal->fresh()->tags);
    }

    public function test_deal_has_stage_history(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
        DealStageHistory::factory()->create([
            'organization_id' => $this->org->id,
            'deal_id' => $deal->id,
            'to_stage_id' => $this->stage->id,
        ]);

        $this->assertCount(1, $deal->stageHistory);
    }

    // --- Status Helpers ---

    public function test_deal_status_helpers(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'status' => 'open',
        ]);

        $this->assertTrue($deal->isOpen());
        $this->assertFalse($deal->isWon());
        $this->assertFalse($deal->isLost());
        $this->assertFalse($deal->isClosed());
    }

    public function test_deal_won_is_closed(): void
    {
        $deal = Deal::factory()->won()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $this->assertTrue($deal->isWon());
        $this->assertTrue($deal->isClosed());
    }

    public function test_deal_lost_is_closed(): void
    {
        $deal = Deal::factory()->lost()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $this->assertTrue($deal->isLost());
        $this->assertTrue($deal->isClosed());
    }

    public function test_deal_time_in_current_stage(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'stage_entered_at' => now()->subHours(2),
        ]);

        $seconds = $deal->timeInCurrentStage();
        $this->assertGreaterThanOrEqual(7200, $seconds);
    }

    // --- Tenant Scope ---

    public function test_deal_tenant_scope(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherPipeline = Pipeline::factory()->create(['organization_id' => $otherOrg->id]);
        $otherStage = PipelineStage::factory()->create([
            'organization_id' => $otherOrg->id,
            'pipeline_id' => $otherPipeline->id,
        ]);

        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
        Deal::factory()->create([
            'organization_id' => $otherOrg->id,
            'pipeline_id' => $otherPipeline->id,
            'pipeline_stage_id' => $otherStage->id,
        ]);

        app()->instance('tenant', $this->org);
        $this->assertCount(1, Deal::all());
    }

    // --- Policy ---

    public function test_policy_org_admin_can_view_all_deals(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'assigned_user_id' => null,
        ]);

        $this->assertTrue($admin->can('view', $deal));
    }

    public function test_policy_agent_can_only_view_assigned_deals(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'assigned_user_id' => $agent->id,
        ]);
        $otherDeal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'assigned_user_id' => null,
        ]);

        $this->assertTrue($agent->can('view', $deal));
        $this->assertFalse($agent->can('view', $otherDeal));
    }

    public function test_policy_agent_can_create_deals(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->assertTrue($agent->can('create', Deal::class));
    }

    public function test_policy_agent_cannot_delete_deals(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'assigned_user_id' => $agent->id,
        ]);

        $this->assertFalse($agent->can('delete', $deal));
    }

    public function test_policy_org_admin_can_delete_deals(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $this->assertTrue($admin->can('delete', $deal));
    }

    public function test_policy_cross_tenant_cannot_view(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $otherUser->assignRole('org_admin');

        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $this->assertFalse($otherUser->can('view', $deal));
    }

    // --- Commission Helpers ---

    public function test_commission_status_helpers(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
        $user = User::factory()->create(['organization_id' => $this->org->id]);

        $pending = DealCommission::factory()->create([
            'organization_id' => $this->org->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $approved = DealCommission::factory()->create([
            'organization_id' => $this->org->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'status' => 'approved',
        ]);
        $paid = DealCommission::factory()->create([
            'organization_id' => $this->org->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'status' => 'paid',
        ]);

        $this->assertTrue($pending->isPending());
        $this->assertTrue($approved->isApproved());
        $this->assertTrue($paid->isPaid());
    }

    // --- Attachment Helpers ---

    public function test_attachment_size_for_humans(): void
    {
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
        $user = User::factory()->create(['organization_id' => $this->org->id]);

        $attachment = DealAttachment::factory()->create([
            'organization_id' => $this->org->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'file_size' => 2097152, // 2 MB
        ]);

        $this->assertEquals('2 MB', $attachment->sizeForHumans());
    }

    // --- Conversation Relation ---

    public function test_conversation_has_deals(): void
    {
        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $channel->id,
        ]);
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'conversation_id' => $conv->id,
        ]);

        $this->assertCount(1, $conv->deals);
    }

    // --- User Relations ---

    public function test_user_has_deals(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'assigned_user_id' => $user->id,
        ]);

        $this->assertCount(1, $user->deals);
    }

    public function test_user_has_commissions(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);
        DealCommission::factory()->create([
            'organization_id' => $this->org->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
        ]);

        $this->assertCount(1, $user->commissions);
    }

    // --- Organization Relations ---

    public function test_organization_has_pipelines(): void
    {
        $this->assertCount(1, $this->org->pipelines);
    }

    public function test_organization_has_deals(): void
    {
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $this->assertCount(1, $this->org->deals);
    }

    public function test_organization_has_tags(): void
    {
        Tag::factory()->create(['organization_id' => $this->org->id]);

        $this->assertCount(1, $this->org->tags);
    }
}
