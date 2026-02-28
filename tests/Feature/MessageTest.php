<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
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

    public function test_message_belongs_to_organization(): void
    {
        $msg = Message::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertEquals($this->org->id, $msg->organization->id);
    }

    public function test_message_belongs_to_conversation(): void
    {
        $msg = Message::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertEquals($this->conversation->id, $msg->conversation->id);
    }

    public function test_message_can_belong_to_user(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $msg = Message::factory()->outbound()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $agent->id,
        ]);

        $this->assertEquals($agent->id, $msg->user->id);
    }

    public function test_message_type_helpers(): void
    {
        $text = Message::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);
        $note = Message::factory()->internalNote()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertTrue($text->isInbound());
        $this->assertFalse($text->isInternalNote());
        $this->assertTrue($note->isInternalNote());
        $this->assertTrue($note->isOutbound());
    }

    public function test_message_metadata_cast_as_array(): void
    {
        $msg = Message::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'metadata' => ['wa_id' => 'abc123'],
        ]);

        $msg->refresh();
        $this->assertIsArray($msg->metadata);
        $this->assertEquals('abc123', $msg->metadata['wa_id']);
    }

    public function test_message_scoped_by_tenant(): void
    {
        $org2 = Organization::factory()->create();
        $ch2 = Channel::factory()->create(['organization_id' => $org2->id]);
        $conv2 = Conversation::factory()->create([
            'organization_id' => $org2->id,
            'channel_id' => $ch2->id,
        ]);

        Message::factory()->count(2)->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);
        Message::factory()->count(3)->create([
            'organization_id' => $org2->id,
            'conversation_id' => $conv2->id,
        ]);

        app()->instance('tenant', $this->org);
        $this->assertCount(2, Message::all());
    }

    // --- Policies ---

    public function test_agent_can_view_messages_of_assigned_conversation(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');
        $this->conversation->update(['assigned_user_id' => $agent->id]);

        $msg = Message::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertTrue($agent->can('view', $msg));
    }

    public function test_agent_cannot_view_messages_of_unassigned_conversation(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $msg = Message::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertFalse($agent->can('view', $msg));
    }

    public function test_cross_tenant_message_access_blocked(): void
    {
        $org2 = Organization::factory()->create();
        $ch2 = Channel::factory()->create(['organization_id' => $org2->id]);
        $conv2 = Conversation::factory()->create([
            'organization_id' => $org2->id,
            'channel_id' => $ch2->id,
        ]);

        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $msg = Message::factory()->create([
            'organization_id' => $org2->id,
            'conversation_id' => $conv2->id,
        ]);

        $this->assertFalse($admin->can('view', $msg));
    }
}
