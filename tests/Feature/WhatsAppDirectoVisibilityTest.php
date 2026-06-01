<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use App\Models\WhatsAppWebSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Visibilidad por asignación en WhatsApp Directo (#4):
 * un agente sin 'conversations.view-all' solo ve/accede a las conversaciones
 * asignadas a él; el admin/supervisor ve todas.
 */
class WhatsAppDirectoVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Channel $channel;
    private User $admin;
    private User $agent;
    private Conversation $assignedToAgent;
    private Conversation $unassigned;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::factory()->create();
        app()->instance('tenant', $this->org);

        $this->channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'type' => 'whatsapp_web',
        ]);
        WhatsAppWebSession::create([
            'channel_id' => $this->channel->id,
            'instance_name' => 'chatme-ch-vis',
            'status' => WhatsAppWebSession::STATUS_CONNECTED,
            'connected_name' => 'Ventas',
        ]);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('org_admin');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        $this->assignedToAgent = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'contact_identifier' => '5210000000001',
            'assigned_user_id' => $this->agent->id,
        ]);

        $this->unassigned = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'contact_identifier' => '5210000000002',
            'assigned_user_id' => null,
        ]);

        $this->domain = 'http://app.' . config('app.base_domain');

        Event::fake();
    }

    private function url(string $path): string
    {
        return "{$this->domain}/whatsapp-directo/{$this->channel->id}{$path}";
    }

    public function test_agent_list_only_shows_assigned(): void
    {
        $res = $this->actingAs($this->agent)->getJson($this->url('/conversations'));
        $res->assertOk();

        $ids = collect($res->json('conversations'))->pluck('id')->all();
        $this->assertContains($this->assignedToAgent->id, $ids);
        $this->assertNotContains($this->unassigned->id, $ids);
    }

    public function test_admin_list_shows_all(): void
    {
        $res = $this->actingAs($this->admin)->getJson($this->url('/conversations'));
        $res->assertOk();

        $ids = collect($res->json('conversations'))->pluck('id')->all();
        $this->assertContains($this->assignedToAgent->id, $ids);
        $this->assertContains($this->unassigned->id, $ids);
    }

    public function test_agent_can_read_assigned_conversation(): void
    {
        $this->actingAs($this->agent)
            ->getJson($this->url("/conversations/{$this->assignedToAgent->id}"))
            ->assertOk();
    }

    public function test_agent_cannot_read_unassigned_conversation(): void
    {
        $this->actingAs($this->agent)
            ->getJson($this->url("/conversations/{$this->unassigned->id}"))
            ->assertForbidden();
    }

    public function test_agent_cannot_send_to_unassigned_conversation(): void
    {
        $this->actingAs($this->agent)
            ->postJson($this->url("/conversations/{$this->unassigned->id}/send"), ['body' => 'hola'])
            ->assertForbidden();
    }

    public function test_admin_can_read_unassigned_conversation(): void
    {
        $this->actingAs($this->admin)
            ->getJson($this->url("/conversations/{$this->unassigned->id}"))
            ->assertOk();
    }

    public function test_agent_forward_targets_only_shows_assigned(): void
    {
        $res = $this->actingAs($this->agent)->getJson($this->url('/forward-targets'));
        $res->assertOk();

        $ids = collect($res->json('targets'))->pluck('id')->all();
        $this->assertContains($this->assignedToAgent->id, $ids);
        $this->assertNotContains($this->unassigned->id, $ids);
    }
}
