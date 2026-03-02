<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelForm;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChannelManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $orgAdmin;
    private User $agent;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::factory()->create();

        $this->orgAdmin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->orgAdmin->assignRole('org_admin');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        $this->domain = 'http://app.' . config('app.base_domain');

        app()->instance('tenant', $this->org);
        Event::fake([BroadcastEvent::class]);
    }

    private function channelUrl(string $path = ''): string
    {
        return "{$this->domain}/settings/channels{$path}";
    }

    public function test_org_admin_can_view_channels(): void
    {
        Channel::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->orgAdmin)
            ->get($this->channelUrl());

        $response->assertStatus(200);
    }

    public function test_org_admin_can_view_create_form(): void
    {
        $response = $this->actingAs($this->orgAdmin)
            ->get($this->channelUrl('/create'));

        $response->assertStatus(200);
    }

    public function test_org_admin_can_create_whatsapp_channel(): void
    {
        $response = $this->actingAs($this->orgAdmin)
            ->post($this->channelUrl(), [
                'name' => 'My WhatsApp',
                'type' => 'whatsapp',
                'phone_number_id' => '1234567890',
                'waba_id' => '0987654321',
                'access_token' => 'EAAtoken123',
                'verify_token' => 'myverifytoken',
                'app_secret' => 'mysecret123',
                'display_phone' => '+521234567890',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('channels', [
            'organization_id' => $this->org->id,
            'name' => 'My WhatsApp',
            'type' => 'whatsapp',
        ]);
    }

    public function test_org_admin_can_create_webchat_channel(): void
    {
        $response = $this->actingAs($this->orgAdmin)
            ->post($this->channelUrl(), [
                'name' => 'My Webchat',
                'type' => 'webchat',
                'allowed_origins' => "https://example.com\nhttps://shop.example.com",
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('channels', [
            'organization_id' => $this->org->id,
            'name' => 'My Webchat',
            'type' => 'webchat',
        ]);
    }

    public function test_org_admin_can_create_facebook_channel(): void
    {
        $response = $this->actingAs($this->orgAdmin)
            ->post($this->channelUrl(), [
                'name' => 'My Facebook',
                'type' => 'facebook',
                'page_id' => '123456789',
                'page_access_token' => 'EAAfbtoken123',
                'app_secret' => 'fbsecret123',
                'verify_token' => 'fbverify123',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('channels', [
            'organization_id' => $this->org->id,
            'name' => 'My Facebook',
            'type' => 'facebook',
        ]);
    }

    public function test_org_admin_can_create_instagram_channel(): void
    {
        $response = $this->actingAs($this->orgAdmin)
            ->post($this->channelUrl(), [
                'name' => 'My Instagram',
                'type' => 'instagram',
                'instagram_account_id' => '987654321',
                'page_id' => '123456789',
                'page_access_token' => 'EAAigtoken123',
                'app_secret' => 'igsecret123',
                'verify_token' => 'igverify123',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('channels', [
            'organization_id' => $this->org->id,
            'name' => 'My Instagram',
            'type' => 'instagram',
        ]);
    }

    public function test_org_admin_can_view_channel(): void
    {
        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->orgAdmin)
            ->get($this->channelUrl("/{$channel->id}"));

        $response->assertStatus(200);
        $response->assertSee($channel->name);
    }

    public function test_org_admin_can_update_channel(): void
    {
        $channel = Channel::factory()->whatsappConfigured()->create([
            'organization_id' => $this->org->id,
        ]);

        $response = $this->actingAs($this->orgAdmin)
            ->post($this->channelUrl("/{$channel->id}/update"), [
                'name' => 'Updated Name',
                'phone_number_id' => '1111111111',
                'waba_id' => '2222222222',
                'verify_token' => 'newtoken',
                'display_phone' => '+529999999999',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('channels', [
            'id' => $channel->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_org_admin_can_toggle_channel(): void
    {
        $channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->orgAdmin)
            ->post($this->channelUrl("/{$channel->id}/toggle"));

        $response->assertRedirect();
        $this->assertFalse($channel->fresh()->is_active);
    }

    public function test_org_admin_can_delete_channel_without_conversations(): void
    {
        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->orgAdmin)
            ->post($this->channelUrl("/{$channel->id}/delete"));

        $response->assertRedirect();
        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    }

    public function test_org_admin_cannot_delete_channel_with_conversations(): void
    {
        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);
        Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $channel->id,
        ]);

        $response = $this->actingAs($this->orgAdmin)
            ->post($this->channelUrl("/{$channel->id}/delete"));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('channels', ['id' => $channel->id]);
    }

    public function test_agent_cannot_create_channel(): void
    {
        $response = $this->actingAs($this->agent)
            ->post($this->channelUrl(), [
                'name' => 'Agent Channel',
                'type' => 'webchat',
            ]);

        $response->assertStatus(403);
    }

    public function test_cross_tenant_isolation(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherChannel = Channel::factory()->create(['organization_id' => $otherOrg->id]);

        $response = $this->actingAs($this->orgAdmin)
            ->get($this->channelUrl("/{$otherChannel->id}"));

        $response->assertStatus(404);
    }

    public function test_webchat_form_template_update(): void
    {
        $channel = Channel::factory()->webchat()->create([
            'organization_id' => $this->org->id,
            'configuration' => ['allowed_origins' => []],
        ]);

        $response = $this->actingAs($this->orgAdmin)
            ->post($this->channelUrl("/{$channel->id}/update"), [
                'name' => $channel->name,
                'allowed_origins' => '',
                'template_key' => 'contacto_basico',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('channel_forms', [
            'channel_id' => $channel->id,
            'template_key' => 'contacto_basico',
        ]);
    }
}
