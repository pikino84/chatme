<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Channel $channel;
    private User $supervisor;
    private User $agent;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::factory()->create();
        $this->channel = Channel::factory()->create(['organization_id' => $this->org->id]);

        $this->supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $this->supervisor->assignRole('supervisor');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        $this->domain = 'http://app.' . config('app.base_domain');

        app()->instance('tenant', $this->org);

        Event::fake();
    }

    private function inboxUrl(string $path = ''): string
    {
        return "{$this->domain}/inbox{$path}";
    }

    public function test_supervisor_can_view_inbox(): void
    {
        Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'contact_identifier' => '+5215500001111',
        ]);

        $response = $this->actingAs($this->supervisor)->get($this->inboxUrl());

        $response->assertOk();
        $response->assertViewIs('inbox.index');
        $response->assertViewHas('conversations');
    }

    public function test_agent_sees_only_assigned_conversations(): void
    {
        Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'assigned_user_id' => $this->agent->id,
            'contact_identifier' => '+5215500002222',
        ]);

        Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'assigned_user_id' => null,
            'contact_identifier' => '+5215500003333',
        ]);

        $response = $this->actingAs($this->agent)->get($this->inboxUrl());

        $response->assertOk();
        $this->assertCount(1, $response->viewData('conversations'));
    }

    public function test_supervisor_can_view_conversation(): void
    {
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'contact_identifier' => '+5215500004444',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get($this->inboxUrl("/conversations/{$conv->id}"));

        $response->assertOk();
        $response->assertViewIs('inbox.show');
    }

    public function test_agent_cannot_view_unassigned_conversation(): void
    {
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'assigned_user_id' => null,
            'contact_identifier' => '+5215500005555',
        ]);

        $response = $this->actingAs($this->agent)
            ->get($this->inboxUrl("/conversations/{$conv->id}"));

        $response->assertForbidden();
    }

    public function test_supervisor_can_close_conversation(): void
    {
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'status' => 'open',
            'contact_identifier' => '+5215500006666',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->post($this->inboxUrl("/conversations/{$conv->id}/close"));

        $response->assertRedirect();
        $this->assertEquals('closed', $conv->fresh()->status);
    }

    public function test_supervisor_can_reopen_conversation(): void
    {
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'status' => 'closed',
            'closed_at' => now(),
            'contact_identifier' => '+5215500007777',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->post($this->inboxUrl("/conversations/{$conv->id}/reopen"));

        $response->assertRedirect();
        $this->assertEquals('open', $conv->fresh()->status);
    }

    public function test_supervisor_can_assign_conversation(): void
    {
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'contact_identifier' => '+5215500008888',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->post($this->inboxUrl("/conversations/{$conv->id}/assign"), [
                'assigned_user_id' => $this->agent->id,
            ]);

        $response->assertRedirect();
        $this->assertEquals($this->agent->id, $conv->fresh()->assigned_user_id);
    }

    public function test_supervisor_can_transfer_conversation(): void
    {
        $agent2 = User::factory()->create(['organization_id' => $this->org->id]);
        $agent2->assignRole('agent');

        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'assigned_user_id' => $this->agent->id,
            'contact_identifier' => '+5215500009999',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->post($this->inboxUrl("/conversations/{$conv->id}/transfer"), [
                'to_user_id' => $agent2->id,
                'reason' => 'Specialist needed',
            ]);

        $response->assertRedirect();
        $this->assertEquals($agent2->id, $conv->fresh()->assigned_user_id);
    }

    public function test_agent_can_send_message(): void
    {
        $conv = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'assigned_user_id' => $this->agent->id,
            'contact_identifier' => '+5215500010000',
        ]);

        $response = $this->actingAs($this->agent)
            ->post($this->inboxUrl("/conversations/{$conv->id}/messages"), [
                'body' => 'Hello customer!',
                'type' => 'text',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conv->id,
            'body' => 'Hello customer!',
            'direction' => 'outbound',
            'user_id' => $this->agent->id,
        ]);
    }

    public function test_cross_tenant_isolation(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherChannel = Channel::factory()->create(['organization_id' => $otherOrg->id]);
        $conv = Conversation::factory()->create([
            'organization_id' => $otherOrg->id,
            'channel_id' => $otherChannel->id,
            'contact_identifier' => '+5215500011111',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get($this->inboxUrl("/conversations/{$conv->id}"));

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_access_inbox(): void
    {
        $response = $this->get($this->inboxUrl());

        $response->assertRedirect();
    }
}
