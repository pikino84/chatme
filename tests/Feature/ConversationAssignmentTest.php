<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationAssignment;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationAssignmentTest extends TestCase
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

    public function test_assignment_belongs_to_organization(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $assignment = ConversationAssignment::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $agent->id,
        ]);

        $this->assertEquals($this->org->id, $assignment->organization->id);
    }

    public function test_assignment_belongs_to_conversation(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $assignment = ConversationAssignment::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $agent->id,
        ]);

        $this->assertEquals($this->conversation->id, $assignment->conversation->id);
    }

    public function test_assignment_belongs_to_user(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $assignment = ConversationAssignment::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $agent->id,
        ]);

        $this->assertEquals($agent->id, $assignment->user->id);
    }

    public function test_assignment_tracks_assigned_by(): void
    {
        $supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $agent = User::factory()->create(['organization_id' => $this->org->id]);

        $assignment = ConversationAssignment::factory()
            ->assignedBy($supervisor)
            ->create([
                'organization_id' => $this->org->id,
                'conversation_id' => $this->conversation->id,
                'user_id' => $agent->id,
            ]);

        $this->assertEquals($supervisor->id, $assignment->assignedByUser->id);
    }

    public function test_assignment_is_active_helper(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);

        $active = ConversationAssignment::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $agent->id,
        ]);
        $inactive = ConversationAssignment::factory()->unassigned()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $agent->id,
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($inactive->isActive());
    }

    public function test_assignment_scoped_by_tenant(): void
    {
        $org2 = Organization::factory()->create();
        $ch2 = Channel::factory()->create(['organization_id' => $org2->id]);
        $conv2 = Conversation::factory()->create([
            'organization_id' => $org2->id,
            'channel_id' => $ch2->id,
        ]);
        $agent1 = User::factory()->create(['organization_id' => $this->org->id]);
        $agent2 = User::factory()->create(['organization_id' => $org2->id]);

        ConversationAssignment::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $agent1->id,
        ]);
        ConversationAssignment::factory()->count(2)->create([
            'organization_id' => $org2->id,
            'conversation_id' => $conv2->id,
            'user_id' => $agent2->id,
        ]);

        app()->instance('tenant', $this->org);
        $this->assertCount(1, ConversationAssignment::all());
    }

    // --- Policies ---

    public function test_supervisor_can_create_assignment(): void
    {
        $supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $supervisor->assignRole('supervisor');

        $this->assertTrue($supervisor->can('create', [ConversationAssignment::class, $this->conversation]));
    }

    public function test_agent_cannot_create_assignment(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->assertFalse($agent->can('create', [ConversationAssignment::class, $this->conversation]));
    }
}
