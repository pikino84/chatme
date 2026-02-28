<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationTransfer;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTransferTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Channel $channel;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->channel = Channel::factory()->create(['organization_id' => $this->org->id]);
        $this->conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);
    }

    // --- Model ---

    public function test_transfer_belongs_to_organization(): void
    {
        $from = User::factory()->create(['organization_id' => $this->org->id]);
        $to = User::factory()->create(['organization_id' => $this->org->id]);

        $transfer = ConversationTransfer::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'transferred_by' => $from->id,
        ]);

        $this->assertEquals($this->org->id, $transfer->organization->id);
    }

    public function test_transfer_has_from_and_to_users(): void
    {
        $from = User::factory()->create(['organization_id' => $this->org->id]);
        $to = User::factory()->create(['organization_id' => $this->org->id]);

        $transfer = ConversationTransfer::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'transferred_by' => $from->id,
        ]);

        $this->assertEquals($from->id, $transfer->fromUser->id);
        $this->assertEquals($to->id, $transfer->toUser->id);
        $this->assertEquals($from->id, $transfer->transferredByUser->id);
    }

    public function test_transfer_belongs_to_conversation(): void
    {
        $from = User::factory()->create(['organization_id' => $this->org->id]);
        $to = User::factory()->create(['organization_id' => $this->org->id]);

        $transfer = ConversationTransfer::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'transferred_by' => $from->id,
        ]);

        $this->assertEquals($this->conversation->id, $transfer->conversation->id);
    }

    public function test_transfer_reason_is_optional(): void
    {
        $from = User::factory()->create(['organization_id' => $this->org->id]);
        $to = User::factory()->create(['organization_id' => $this->org->id]);

        $transfer = ConversationTransfer::factory()->withoutReason()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'transferred_by' => $from->id,
        ]);

        $this->assertNull($transfer->reason);
    }

    public function test_transfer_scoped_by_tenant(): void
    {
        $org2 = Organization::factory()->create();
        $ch2 = Channel::factory()->create(['organization_id' => $org2->id]);
        $conv2 = Conversation::factory()->create([
            'organization_id' => $org2->id,
            'channel_id' => $ch2->id,
        ]);

        $u1 = User::factory()->create(['organization_id' => $this->org->id]);
        $u2 = User::factory()->create(['organization_id' => $this->org->id]);
        $u3 = User::factory()->create(['organization_id' => $org2->id]);
        $u4 = User::factory()->create(['organization_id' => $org2->id]);

        ConversationTransfer::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'from_user_id' => $u1->id,
            'to_user_id' => $u2->id,
            'transferred_by' => $u1->id,
        ]);
        ConversationTransfer::factory()->count(2)->create([
            'organization_id' => $org2->id,
            'conversation_id' => $conv2->id,
            'from_user_id' => $u3->id,
            'to_user_id' => $u4->id,
            'transferred_by' => $u3->id,
        ]);

        app()->instance('tenant', $this->org);
        $this->assertCount(1, ConversationTransfer::all());
    }

    // --- Policies ---

    public function test_supervisor_can_create_transfer(): void
    {
        $supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $supervisor->assignRole('supervisor');

        $this->assertTrue($supervisor->can('create', [ConversationTransfer::class, $this->conversation]));
    }

    public function test_agent_cannot_create_transfer(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->assertFalse($agent->can('create', [ConversationTransfer::class, $this->conversation]));
    }
}
