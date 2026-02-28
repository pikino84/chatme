<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelForm;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use App\Services\WebchatTokenService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChannelFormTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Channel $channel;
    private User $admin;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::factory()->create();
        $this->channel = Channel::factory()->webchat()->create([
            'organization_id' => $this->org->id,
        ]);

        $this->admin = User::factory()->create(['organization_id' => null]);
        $this->admin->assignRole('saas_admin');

        $this->domain = 'admin.' . config('app.base_domain');
    }

    private function adminGet(string $uri)
    {
        return $this->actingAs($this->admin)
            ->get("http://{$this->domain}/panel{$uri}");
    }

    private function adminPost(string $uri, array $data = [])
    {
        return $this->actingAs($this->admin)
            ->post("http://{$this->domain}/panel{$uri}", $data);
    }

    private function adminDelete(string $uri)
    {
        return $this->actingAs($this->admin)
            ->delete("http://{$this->domain}/panel{$uri}");
    }

    // ── Model & Config ──

    public function test_form_templates_config_has_three_templates(): void
    {
        $templates = config('form_templates');

        $this->assertArrayHasKey('contacto_basico', $templates);
        $this->assertArrayHasKey('muebleria', $templates);
        $this->assertArrayHasKey('agencia_viajes', $templates);
    }

    public function test_each_template_has_name_and_fields(): void
    {
        foreach (config('form_templates') as $key => $template) {
            $this->assertArrayHasKey('name', $template, "Template {$key} missing 'name'");
            $this->assertArrayHasKey('fields', $template, "Template {$key} missing 'fields'");
            $this->assertNotEmpty($template['fields'], "Template {$key} has no fields");
        }
    }

    public function test_channel_form_model_casts_schema_as_array(): void
    {
        $form = ChannelForm::factory()->create([
            'channel_id' => $this->channel->id,
        ]);

        $this->assertIsArray($form->schema);
        $this->assertIsBool($form->is_active);
    }

    public function test_channel_form_belongs_to_channel(): void
    {
        $form = ChannelForm::factory()->create([
            'channel_id' => $this->channel->id,
        ]);

        $this->assertTrue($form->channel->is($this->channel));
    }

    public function test_channel_has_one_form(): void
    {
        $form = ChannelForm::factory()->create([
            'channel_id' => $this->channel->id,
        ]);

        $this->assertTrue($this->channel->form->is($form));
    }

    public function test_is_from_template_returns_true_when_template_key_set(): void
    {
        $form = ChannelForm::factory()->create([
            'channel_id' => $this->channel->id,
        ]);

        $this->assertTrue($form->isFromTemplate());
    }

    public function test_is_from_template_returns_false_for_custom(): void
    {
        $form = ChannelForm::factory()->customSchema([
            'fields' => [['key' => 'test', 'label' => 'Test', 'type' => 'text']],
        ])->create([
            'channel_id' => $this->channel->id,
        ]);

        $this->assertFalse($form->isFromTemplate());
    }

    public function test_get_public_schema_returns_fields_and_template(): void
    {
        $form = ChannelForm::factory()->create([
            'channel_id' => $this->channel->id,
        ]);

        $public = $form->getPublicSchema();

        $this->assertArrayHasKey('fields', $public);
        $this->assertArrayHasKey('template', $public);
        $this->assertEquals('contacto_basico', $public['template']);
    }

    public function test_factory_states_work(): void
    {
        $muebleria = ChannelForm::factory()->muebleria()->make();
        $this->assertEquals('muebleria', $muebleria->template_key);

        $viajes = ChannelForm::factory()->agenciaViajes()->make();
        $this->assertEquals('agencia_viajes', $viajes->template_key);

        $inactive = ChannelForm::factory()->inactive()->make();
        $this->assertFalse($inactive->is_active);
    }

    // ── Public API: Form Schema Retrieval ──

    public function test_form_schema_endpoint_returns_form_when_active(): void
    {
        ChannelForm::factory()->create([
            'channel_id' => $this->channel->id,
        ]);

        $response = $this->getJson("/api/webchat/{$this->channel->uuid}/form-schema");

        $response->assertOk();
        $response->assertJsonStructure(['form' => ['fields', 'template']]);
        $response->assertJsonPath('form.template', 'contacto_basico');
    }

    public function test_form_schema_returns_null_when_no_form(): void
    {
        $response = $this->getJson("/api/webchat/{$this->channel->uuid}/form-schema");

        $response->assertOk();
        $response->assertJsonPath('form', null);
    }

    public function test_form_schema_returns_null_when_form_inactive(): void
    {
        ChannelForm::factory()->inactive()->create([
            'channel_id' => $this->channel->id,
        ]);

        $response = $this->getJson("/api/webchat/{$this->channel->uuid}/form-schema");

        $response->assertOk();
        $response->assertJsonPath('form', null);
    }

    public function test_form_schema_404_for_unknown_channel(): void
    {
        $response = $this->getJson('/api/webchat/00000000-0000-0000-0000-000000000000/form-schema');

        $response->assertNotFound();
    }

    public function test_form_schema_404_for_inactive_channel(): void
    {
        $this->channel->update(['is_active' => false]);

        $response = $this->getJson("/api/webchat/{$this->channel->uuid}/form-schema");

        $response->assertNotFound();
    }

    public function test_form_schema_404_for_non_webchat_channel(): void
    {
        $waChannel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'type' => 'whatsapp',
        ]);

        $response = $this->getJson("/api/webchat/{$waChannel->uuid}/form-schema");

        $response->assertNotFound();
    }

    // ── Origin Validation ──

    public function test_form_schema_validates_origin_when_configured(): void
    {
        $this->channel->update([
            'configuration' => ['allowed_origins' => ['https://empresa.com']],
        ]);

        ChannelForm::factory()->create(['channel_id' => $this->channel->id]);

        $response = $this->getJson(
            "/api/webchat/{$this->channel->uuid}/form-schema",
            ['Origin' => 'https://malicious.com']
        );

        $response->assertForbidden();
    }

    public function test_form_schema_allows_valid_origin(): void
    {
        $this->channel->update([
            'configuration' => ['allowed_origins' => ['https://empresa.com']],
        ]);

        ChannelForm::factory()->create(['channel_id' => $this->channel->id]);

        $response = $this->getJson(
            "/api/webchat/{$this->channel->uuid}/form-schema",
            ['Origin' => 'https://empresa.com']
        );

        $response->assertOk();
        $response->assertJsonPath('form.template', 'contacto_basico');
    }

    // ── Tenant Isolation ──

    public function test_form_schema_only_returns_own_channel_form(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherChannel = Channel::factory()->webchat()->create([
            'organization_id' => $otherOrg->id,
        ]);

        ChannelForm::factory()->muebleria()->create([
            'channel_id' => $otherChannel->id,
        ]);

        // Our channel has no form
        $response = $this->getJson("/api/webchat/{$this->channel->uuid}/form-schema");
        $response->assertOk();
        $response->assertJsonPath('form', null);

        // Other channel returns its own form
        $response2 = $this->getJson("/api/webchat/{$otherChannel->uuid}/form-schema");
        $response2->assertOk();
        $response2->assertJsonPath('form.template', 'muebleria');
    }

    // ── Form Submission with Metadata ──

    public function test_send_message_with_form_data_stores_metadata(): void
    {
        Event::fake([
            \App\Events\ConversationCreated::class,
            \App\Events\MessageReceivedEvent::class,
        ]);

        ChannelForm::factory()->create(['channel_id' => $this->channel->id]);

        $tokenService = app(WebchatTokenService::class);
        $session = $tokenService->create($this->org->id, $this->channel->id);

        $response = $this->postJson("/api/webchat/{$this->channel->uuid}/messages", [
            'body' => 'Hola, necesito información',
            'form_data' => [
                'name' => 'Juan Pérez',
                'email' => 'juan@test.com',
                'message' => 'Quiero saber más',
            ],
        ], [
            'X-Webchat-Token' => $session['token'],
        ]);

        $response->assertCreated();

        $conversation = Conversation::withoutGlobalScopes()->latest()->first();
        $this->assertEquals('Juan Pérez', $conversation->contact_name);
        $this->assertEquals('juan@test.com', $conversation->metadata['form_data']['email']);
        $this->assertEquals('widget_form', $conversation->metadata['source']);
    }

    public function test_send_message_without_form_data_uses_default_name(): void
    {
        Event::fake([
            \App\Events\ConversationCreated::class,
            \App\Events\MessageReceivedEvent::class,
        ]);

        $tokenService = app(WebchatTokenService::class);
        $session = $tokenService->create($this->org->id, $this->channel->id);

        $response = $this->postJson("/api/webchat/{$this->channel->uuid}/messages", [
            'body' => 'Hola',
        ], [
            'X-Webchat-Token' => $session['token'],
        ]);

        $response->assertCreated();

        $conversation = Conversation::withoutGlobalScopes()->latest()->first();
        $this->assertEquals('Visitante Web', $conversation->contact_name);
    }

    public function test_form_data_validation_rejects_non_string_values(): void
    {
        Event::fake();

        $tokenService = app(WebchatTokenService::class);
        $session = $tokenService->create($this->org->id, $this->channel->id);

        $response = $this->postJson("/api/webchat/{$this->channel->uuid}/messages", [
            'body' => 'Test',
            'form_data' => [
                'name' => ['nested' => 'array'],
            ],
        ], [
            'X-Webchat-Token' => $session['token'],
        ]);

        $response->assertUnprocessable();
    }

    // ── Backoffice: Channel Forms Management ──

    public function test_admin_can_list_channel_forms(): void
    {
        ChannelForm::factory()->create(['channel_id' => $this->channel->id]);

        $response = $this->adminGet('/channel-forms');

        $response->assertOk();
        $response->assertSee($this->channel->name);
    }

    public function test_admin_can_view_create_page(): void
    {
        $response = $this->adminGet('/channel-forms/create');

        $response->assertOk();
        $response->assertSee('Assign Form');
    }

    public function test_admin_can_assign_form_to_channel(): void
    {
        $response = $this->adminPost('/channel-forms', [
            'channel_id' => $this->channel->id,
            'template_key' => 'muebleria',
        ]);

        $response->assertRedirect(route('saas-admin.channel-forms.index'));
        $this->assertDatabaseHas('channel_forms', [
            'channel_id' => $this->channel->id,
            'template_key' => 'muebleria',
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_assign_invalid_template(): void
    {
        $response = $this->adminPost('/channel-forms', [
            'channel_id' => $this->channel->id,
            'template_key' => 'nonexistent_template',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('channel_forms', [
            'channel_id' => $this->channel->id,
        ]);
    }

    public function test_admin_cannot_assign_duplicate_form(): void
    {
        ChannelForm::factory()->create(['channel_id' => $this->channel->id]);

        $response = $this->adminPost('/channel-forms', [
            'channel_id' => $this->channel->id,
            'template_key' => 'muebleria',
        ]);

        $response->assertSessionHasErrors('channel_id');
    }

    public function test_admin_can_view_form_details(): void
    {
        $form = ChannelForm::factory()->create(['channel_id' => $this->channel->id]);

        $response = $this->adminGet("/channel-forms/{$form->id}");

        $response->assertOk();
        $response->assertSee('contacto_basico');
        $response->assertSee('Schema (read-only)');
    }

    public function test_admin_can_toggle_form_active(): void
    {
        $form = ChannelForm::factory()->create(['channel_id' => $this->channel->id]);
        $this->assertTrue($form->is_active);

        $this->adminPost("/channel-forms/{$form->id}/toggle");

        $form->refresh();
        $this->assertFalse($form->is_active);

        $this->adminPost("/channel-forms/{$form->id}/toggle");

        $form->refresh();
        $this->assertTrue($form->is_active);
    }

    public function test_admin_can_delete_form(): void
    {
        $form = ChannelForm::factory()->create(['channel_id' => $this->channel->id]);

        $response = $this->adminDelete("/channel-forms/{$form->id}");

        $response->assertRedirect(route('saas-admin.channel-forms.index'));
        $this->assertDatabaseMissing('channel_forms', ['id' => $form->id]);
    }

    public function test_admin_create_page_excludes_channels_with_forms(): void
    {
        ChannelForm::factory()->create(['channel_id' => $this->channel->id]);

        $channel2 = Channel::factory()->webchat()->create([
            'organization_id' => $this->org->id,
            'name' => 'Available Channel',
        ]);

        $response = $this->adminGet('/channel-forms/create');

        $response->assertOk();
        $response->assertSee('Available Channel');
        $response->assertDontSee($this->channel->name);
    }

    public function test_store_validation_requires_channel_and_template(): void
    {
        $response = $this->adminPost('/channel-forms', []);

        $response->assertSessionHasErrors(['channel_id', 'template_key']);
    }

    // ── Backoffice: Sidebar link ──

    public function test_admin_layout_has_channel_forms_link(): void
    {
        $response = $this->adminGet('/');

        $response->assertOk();
        $response->assertSee('Channel Forms');
    }
}
