<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelWizardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansAndFeaturesSeeder::class);

        $this->org = Organization::factory()->create();
        app()->instance('tenant', $this->org);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('org_admin');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        // Give org a subscription so billing checks pass
        $plan = \App\Models\Plan::where('slug', 'enterprise')->first();
        (new \App\Services\BillingService())->subscribe($this->org, $plan);

        // Default pipeline so webchat channels can be created (leads need a place to land)
        \App\Models\Pipeline::factory()->default()->create(['organization_id' => $this->org->id]);
    }

    public function test_org_admin_can_access_wizard(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings.channels.wizard'));

        $response->assertOk();
        $response->assertViewIs('settings.channels.wizard');
        $response->assertViewHasAll(['canCreate', 'webchatEnabled', 'whatsappEnabled', 'brands']);
    }

    public function test_agent_cannot_access_wizard(): void
    {
        $response = $this->actingAs($this->agent)->get(route('settings.channels.wizard'));

        $response->assertForbidden();
    }

    public function test_validate_whatsapp_credentials_success(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'verified_name' => 'Mi Empresa',
                'display_phone_number' => '+52 555 123 4567',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.validate'), [
            'type' => 'whatsapp',
            'phone_number_id' => '123456',
            'access_token' => 'EAA_fake_token',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('details.display_name', 'Mi Empresa');
    }

    public function test_validate_whatsapp_credentials_failure(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid OAuth access token'],
            ], 401),
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.validate'), [
            'type' => 'whatsapp',
            'phone_number_id' => '123456',
            'access_token' => 'bad_token',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => false]);
        $response->assertJsonFragment(['message' => 'Invalid OAuth access token']);
    }

    public function test_validate_facebook_credentials_success(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'name' => 'Mi Página',
                'id' => '123456',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.validate'), [
            'type' => 'facebook',
            'page_id' => '123456',
            'page_access_token' => 'EAA_fake_token',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('details.page_name', 'Mi Página');
    }

    public function test_validate_instagram_credentials_success(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'name' => 'Mi Cuenta IG',
                'username' => 'micuenta',
                'id' => '17841400000',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.validate'), [
            'type' => 'instagram',
            'instagram_account_id' => '17841400000',
            'page_access_token' => 'EAA_fake_token',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('details.username', 'micuenta');
    }

    public function test_validate_webchat_valid_origins(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.validate'), [
            'type' => 'webchat',
            'allowed_origins' => "https://example.com\nhttps://shop.example.com",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_validate_webchat_invalid_origins(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.validate'), [
            'type' => 'webchat',
            'allowed_origins' => "not-a-url",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => false]);
    }

    public function test_wizard_store_creates_whatsapp_channel(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.store'), [
            'type' => 'whatsapp',
            'name' => 'WhatsApp Ventas',
            'phone_number_id' => '123456',
            'waba_id' => '789012',
            'access_token' => 'EAA_test_token',
            'verify_token' => 'my-secret',
            'app_secret' => 'app_secret_123',
            'display_phone' => '+52 555 123 4567',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['channel' => ['id', 'uuid', 'name', 'type'], 'webhook_url', 'show_url']);

        $this->assertDatabaseHas('channels', [
            'organization_id' => $this->org->id,
            'name' => 'WhatsApp Ventas',
            'type' => 'whatsapp',
            'is_active' => true,
        ]);

        $this->assertStringContains('/api/webhooks/whatsapp/', $response->json('webhook_url'));
    }

    public function test_wizard_store_creates_webchat_channel(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.store'), [
            'type' => 'webchat',
            'name' => 'Chat Web',
            'allowed_origins' => "https://example.com",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['widget_snippet']);

        $this->assertDatabaseHas('channels', [
            'organization_id' => $this->org->id,
            'name' => 'Chat Web',
            'type' => 'webchat',
        ]);
    }

    public function test_validate_webchat_blocked_without_pipeline(): void
    {
        \App\Models\Pipeline::query()->delete();

        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.validate'), [
            'type' => 'webchat',
            'allowed_origins' => 'https://example.com',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => false]);
        $response->assertJsonFragment(['message' => $this->pipelineMessage()]);
    }

    public function test_wizard_store_webchat_blocked_without_pipeline(): void
    {
        \App\Models\Pipeline::query()->delete();

        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.store'), [
            'type' => 'webchat',
            'name' => 'Chat Web',
            'allowed_origins' => 'https://example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $response->assertJsonFragment(['message' => $this->pipelineMessage()]);

        $this->assertDatabaseMissing('channels', [
            'organization_id' => $this->org->id,
            'type' => 'webchat',
        ]);
    }

    private function pipelineMessage(): string
    {
        return 'Primero crea un embudo (pipeline) donde aterricen los contactos del chat web. Ve a CRM › Pipelines, crea uno y vuelve a este paso.';
    }

    public function test_wizard_store_creates_facebook_channel(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.store'), [
            'type' => 'facebook',
            'name' => 'FB Messenger',
            'page_id' => '123456',
            'page_access_token' => 'EAA_test',
            'app_secret' => 'secret_123',
            'verify_token' => 'my-fb-token',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertStringContains('/api/webhooks/facebook/', $response->json('webhook_url'));
    }

    public function test_wizard_store_creates_instagram_channel(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.store'), [
            'type' => 'instagram',
            'name' => 'Instagram DM',
            'instagram_account_id' => '17841400000',
            'page_id' => '123456',
            'page_access_token' => 'EAA_test',
            'app_secret' => 'secret_123',
            'verify_token' => 'my-ig-token',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertStringContains('/api/webhooks/instagram/', $response->json('webhook_url'));
    }

    public function test_wizard_store_with_brand(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.store'), [
            'type' => 'webchat',
            'name' => 'Chat Marca',
            'brand_id' => $brand->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('channels', [
            'name' => 'Chat Marca',
            'brand_id' => $brand->id,
        ]);
    }

    public function test_wizard_store_requires_permission(): void
    {
        $response = $this->actingAs($this->agent)->postJson(route('settings.channels.wizard.store'), [
            'type' => 'webchat',
            'name' => 'Test',
        ]);

        $response->assertForbidden();
    }

    public function test_wizard_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.store'), [
            'type' => 'whatsapp',
            'name' => 'Test',
            // missing required whatsapp fields
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_wizard_store_tenant_isolation(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $otherUser->assignRole('org_admin');

        // Create channel as admin of org1
        $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.store'), [
            'type' => 'webchat',
            'name' => 'Org1 Chat',
        ]);

        // Switch tenant context
        app()->instance('tenant', $otherOrg);

        // Org2 should not see org1's channel
        $this->assertDatabaseMissing('channels', [
            'organization_id' => $otherOrg->id,
            'name' => 'Org1 Chat',
        ]);
    }

    public function test_billing_limit_blocks_creation(): void
    {
        // Give org a starter plan with max_channels = 1
        $starterPlan = \App\Models\Plan::where('slug', 'starter')->first();
        \App\Models\OrganizationSubscription::where('organization_id', $this->org->id)->delete();
        (new \App\Services\BillingService())->subscribe($this->org, $starterPlan);

        // Create one channel to hit the limit
        Channel::factory()->create(['organization_id' => $this->org->id, 'type' => 'whatsapp']);

        // Try to create another
        $response = $this->actingAs($this->admin)->postJson(route('settings.channels.wizard.store'), [
            'type' => 'webchat',
            'name' => 'Second Channel',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Has alcanzado el límite de canales de tu plan.']);
    }

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
