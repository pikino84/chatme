<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationSlaLog;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationSlaLogTest extends TestCase
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

    public function test_sla_log_belongs_to_organization(): void
    {
        $sla = ConversationSlaLog::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertEquals($this->org->id, $sla->organization->id);
    }

    public function test_sla_log_belongs_to_conversation(): void
    {
        $sla = ConversationSlaLog::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertEquals($this->conversation->id, $sla->conversation->id);
    }

    public function test_sla_log_breached_helper(): void
    {
        $ok = ConversationSlaLog::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);
        $breached = ConversationSlaLog::factory()->breached()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertFalse($ok->isBreached());
        $this->assertTrue($breached->isBreached());
        $this->assertNotNull($breached->breached_at);
    }

    public function test_sla_log_resolution_metric(): void
    {
        $sla = ConversationSlaLog::factory()->resolution()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertEquals('resolution', $sla->metric);
        $this->assertEquals(3600, $sla->target_seconds);
    }

    public function test_sla_log_casts_integers(): void
    {
        $sla = ConversationSlaLog::factory()->breached()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertIsInt($sla->target_seconds);
        $this->assertIsInt($sla->actual_seconds);
        $this->assertIsBool($sla->breached);
    }

    public function test_sla_log_scoped_by_tenant(): void
    {
        $org2 = Organization::factory()->create();
        $ch2 = Channel::factory()->create(['organization_id' => $org2->id]);
        $conv2 = Conversation::factory()->create([
            'organization_id' => $org2->id,
            'channel_id' => $ch2->id,
        ]);

        ConversationSlaLog::factory()->count(2)->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);
        ConversationSlaLog::factory()->count(3)->create([
            'organization_id' => $org2->id,
            'conversation_id' => $conv2->id,
        ]);

        app()->instance('tenant', $this->org);
        $this->assertCount(2, ConversationSlaLog::all());
    }

    // --- Policies ---

    public function test_supervisor_can_view_sla_logs(): void
    {
        $supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $supervisor->assignRole('supervisor');

        $sla = ConversationSlaLog::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertTrue($supervisor->can('view', $sla));
    }

    public function test_agent_cannot_view_sla_logs(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $sla = ConversationSlaLog::factory()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
        ]);

        $this->assertFalse($agent->can('view', $sla));
    }

    public function test_cross_tenant_sla_log_access_blocked(): void
    {
        $org2 = Organization::factory()->create();
        $ch2 = Channel::factory()->create(['organization_id' => $org2->id]);
        $conv2 = Conversation::factory()->create([
            'organization_id' => $org2->id,
            'channel_id' => $ch2->id,
        ]);

        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $sla = ConversationSlaLog::factory()->create([
            'organization_id' => $org2->id,
            'conversation_id' => $conv2->id,
        ]);

        $this->assertFalse($admin->can('view', $sla));
    }
}
