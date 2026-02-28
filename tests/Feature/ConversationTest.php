<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->channel = Channel::factory()->create(['organization_id' => $this->org->id]);
    }

    // --- Model ---

    public function test_conversation_belongs_to_organization(): void
    {
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        $this->assertEquals($this->org->id, $conv->organization->id);
    }

    public function test_conversation_belongs_to_channel(): void
    {
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        $this->assertEquals($this->channel->id, $conv->channel->id);
    }

    public function test_conversation_can_be_assigned_to_user(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'assigned_user_id' => $agent->id,
        ]);

        $this->assertEquals($agent->id, $conv->assignedUser->id);
        $this->assertTrue($conv->isAssignedTo($agent));
    }

    public function test_conversation_status_helpers(): void
    {
        $open = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);
        $closed = Conversation::factory()->closed()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        $this->assertTrue($open->isOpen());
        $this->assertFalse($open->isClosed());
        $this->assertTrue($closed->isClosed());
        $this->assertNotNull($closed->closed_at);
    }

    public function test_conversation_scoped_by_tenant(): void
    {
        $org2 = Organization::factory()->create();
        $ch2 = Channel::factory()->create(['organization_id' => $org2->id]);

        Conversation::factory()->count(2)->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);
        Conversation::factory()->count(3)->create([
            'organization_id' => $org2->id,
            'channel_id' => $ch2->id,
        ]);

        app()->instance('tenant', $this->org);
        $this->assertCount(2, Conversation::all());
    }

    // --- Policies ---

    public function test_agent_can_view_assigned_conversation(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'assigned_user_id' => $agent->id,
        ]);

        $this->assertTrue($agent->can('view', $conv));
    }

    public function test_agent_cannot_view_unassigned_conversation(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        $this->assertFalse($agent->can('view', $conv));
    }

    public function test_supervisor_can_view_all_conversations(): void
    {
        $supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $supervisor->assignRole('supervisor');

        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        $this->assertTrue($supervisor->can('view', $conv));
    }

    public function test_agent_can_close_own_conversation(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'assigned_user_id' => $agent->id,
        ]);

        $this->assertTrue($agent->can('close', $conv));
    }

    public function test_agent_cannot_close_other_conversation(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        $this->assertFalse($agent->can('close', $conv));
    }

    public function test_agent_cannot_assign_conversations(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        $this->assertFalse($agent->can('assign', $conv));
    }

    public function test_supervisor_can_assign_conversations(): void
    {
        $supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $supervisor->assignRole('supervisor');

        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        $this->assertTrue($supervisor->can('assign', $conv));
    }

    public function test_conversation_delete_always_denied(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        $this->assertFalse($admin->can('delete', $conv));
    }

    public function test_cross_tenant_conversation_access_blocked(): void
    {
        $org2 = Organization::factory()->create();
        $ch2 = Channel::factory()->create(['organization_id' => $org2->id]);

        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $conv = Conversation::factory()->create([
            'organization_id' => $org2->id,
            'channel_id' => $ch2->id,
        ]);

        $this->assertFalse($admin->can('view', $conv));
        $this->assertFalse($admin->can('close', $conv));
        $this->assertFalse($admin->can('assign', $conv));
    }
}
