<?php

namespace Tests\Feature;

use App\Events\ConversationAssignedEvent;
use App\Events\ConversationClosedEvent;
use App\Events\ConversationCreated;
use App\Events\ConversationReopenedEvent;
use App\Events\ConversationTransferredEvent;
use App\Events\MessageReceivedEvent;
use App\Events\MessageSentEvent;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastEventTest extends TestCase
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

    // --- Event broadcast channels ---

    public function test_conversation_created_broadcasts_to_organization_channel(): void
    {
        $event = new ConversationCreated($this->conversation);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals("private-organization.{$this->org->id}", $channels[0]->name);
    }

    public function test_conversation_assigned_broadcasts_to_org_and_user(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $event = new ConversationAssignedEvent($this->conversation, $agent->id);
        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertEquals("private-organization.{$this->org->id}", $channels[0]->name);
        $this->assertEquals("private-user.{$this->org->id}.{$agent->id}", $channels[1]->name);
    }

    public function test_conversation_transferred_broadcasts_to_org_and_both_users(): void
    {
        $from = User::factory()->create(['organization_id' => $this->org->id]);
        $to = User::factory()->create(['organization_id' => $this->org->id]);
        $event = new ConversationTransferredEvent($this->conversation, $from->id, $to->id);
        $channels = $event->broadcastOn();

        $this->assertCount(3, $channels);
        $this->assertEquals("private-organization.{$this->org->id}", $channels[0]->name);
        $this->assertEquals("private-user.{$this->org->id}.{$from->id}", $channels[1]->name);
        $this->assertEquals("private-user.{$this->org->id}.{$to->id}", $channels[2]->name);
    }

    public function test_conversation_closed_broadcasts_to_org_and_assigned_user(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->conversation->update(['assigned_user_id' => $agent->id]);

        $event = new ConversationClosedEvent($this->conversation, $agent->id);
        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertEquals("private-organization.{$this->org->id}", $channels[0]->name);
        $this->assertEquals("private-user.{$this->org->id}.{$agent->id}", $channels[1]->name);
    }

    public function test_conversation_reopened_broadcasts_to_organization(): void
    {
        $event = new ConversationReopenedEvent($this->conversation);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals("private-organization.{$this->org->id}", $channels[0]->name);
    }

    public function test_message_received_broadcasts_to_conversation_channel(): void
    {
        $msg = Message::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $event = new MessageReceivedEvent($msg);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals(
            "private-conversation.{$this->org->id}.{$this->conversation->id}",
            $channels[0]->name
        );
    }

    public function test_message_sent_broadcasts_to_conversation_channel(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $msg = Message::factory()->outbound()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $agent->id,
        ]);

        $event = new MessageSentEvent($msg);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals(
            "private-conversation.{$this->org->id}.{$this->conversation->id}",
            $channels[0]->name
        );
    }

    // --- Broadcast payload ---

    public function test_conversation_created_payload_excludes_organization_id(): void
    {
        $event = new ConversationCreated($this->conversation);
        $payload = $event->broadcastWith();

        $this->assertArrayNotHasKey('organization_id', $payload);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('contact_name', $payload);
        $this->assertArrayHasKey('status', $payload);
    }

    public function test_message_received_payload_excludes_organization_id(): void
    {
        $msg = Message::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $event = new MessageReceivedEvent($msg);
        $payload = $event->broadcastWith();

        $this->assertArrayNotHasKey('organization_id', $payload);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('body', $payload);
        $this->assertArrayHasKey('direction', $payload);
    }

    // --- Channel authorization tenant isolation ---

    public function test_supervisor_can_subscribe_to_own_organization_channel(): void
    {
        $supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $supervisor->assignRole('supervisor');

        $this->actingAs($supervisor)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-organization.{$this->org->id}",
            ])
            ->assertSuccessful();
    }

    public function test_supervisor_cannot_subscribe_to_other_organization_channel(): void
    {
        $org2 = Organization::factory()->create();
        $supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $supervisor->assignRole('supervisor');

        $this->actingAs($supervisor)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-organization.{$org2->id}",
            ])
            ->assertForbidden();
    }

    public function test_agent_cannot_subscribe_to_organization_channel(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->actingAs($agent)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-organization.{$this->org->id}",
            ])
            ->assertForbidden();
    }

    public function test_agent_can_subscribe_to_assigned_conversation_channel(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');
        $this->conversation->update(['assigned_user_id' => $agent->id]);

        $this->actingAs($agent)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-conversation.{$this->org->id}.{$this->conversation->id}",
            ])
            ->assertSuccessful();
    }

    public function test_agent_cannot_subscribe_to_unassigned_conversation_channel(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->actingAs($agent)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-conversation.{$this->org->id}.{$this->conversation->id}",
            ])
            ->assertForbidden();
    }

    public function test_user_can_subscribe_to_own_user_channel(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->actingAs($agent)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-user.{$this->org->id}.{$agent->id}",
            ])
            ->assertSuccessful();
    }

    public function test_user_cannot_subscribe_to_other_user_channel(): void
    {
        $agent1 = User::factory()->create(['organization_id' => $this->org->id]);
        $agent2 = User::factory()->create(['organization_id' => $this->org->id]);
        $agent1->assignRole('agent');

        $this->actingAs($agent1)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-user.{$this->org->id}.{$agent2->id}",
            ])
            ->assertForbidden();
    }

    public function test_cross_tenant_conversation_channel_blocked(): void
    {
        $org2 = Organization::factory()->create();
        $agent = User::factory()->create(['organization_id' => $org2->id]);
        $agent->assignRole('agent');

        $this->actingAs($agent)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-conversation.{$this->org->id}.{$this->conversation->id}",
            ])
            ->assertForbidden();
    }

    public function test_cross_tenant_user_channel_blocked(): void
    {
        $org2 = Organization::factory()->create();
        $agentOrg1 = User::factory()->create(['organization_id' => $this->org->id]);
        $agentOrg2 = User::factory()->create(['organization_id' => $org2->id]);
        $agentOrg2->assignRole('agent');

        $this->actingAs($agentOrg2)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-user.{$this->org->id}.{$agentOrg1->id}",
            ])
            ->assertForbidden();
    }
}
