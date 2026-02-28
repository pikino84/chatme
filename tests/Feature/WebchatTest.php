<?php

namespace Tests\Feature;

use App\Events\ConversationCreated;
use App\Events\MessageReceivedEvent;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Services\WebchatTokenService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebchatTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Channel $channel;
    private WebchatTokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->channel = Channel::factory()->webchat()->create([
            'organization_id' => $this->org->id,
        ]);
        $this->tokenService = app(WebchatTokenService::class);
    }

    // --- Session creation ---

    public function test_create_session_returns_token(): void
    {
        $response = $this->postJson("/api/webchat/{$this->channel->uuid}/session");

        $response->assertOk();
        $response->assertJsonStructure(['token', 'session_id']);
    }

    public function test_create_session_fails_for_unknown_channel(): void
    {
        $fakeUuid = (string) Str::uuid();
        $response = $this->postJson("/api/webchat/{$fakeUuid}/session");

        $response->assertNotFound();
    }

    public function test_create_session_fails_for_whatsapp_channel(): void
    {
        $waChannel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'type' => 'whatsapp',
        ]);

        $response = $this->postJson("/api/webchat/{$waChannel->uuid}/session");

        $response->assertNotFound();
    }

    public function test_create_session_fails_for_inactive_channel(): void
    {
        $this->channel->update(['is_active' => false]);

        $response = $this->postJson("/api/webchat/{$this->channel->uuid}/session");

        $response->assertNotFound();
    }

    public function test_create_session_validates_origin_when_configured(): void
    {
        $this->channel->update([
            'configuration' => ['allowed_origins' => ['https://empresa.com']],
        ]);

        // Request without Origin header
        $response = $this->postJson("/api/webchat/{$this->channel->uuid}/session");
        $response->assertForbidden();

        // Request with wrong Origin
        $response = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/session",
            [],
            ['Origin' => 'https://evil.com']
        );
        $response->assertForbidden();

        // Request with correct Origin
        $response = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/session",
            [],
            ['Origin' => 'https://empresa.com']
        );
        $response->assertOk();
    }

    // --- Token validation ---

    public function test_token_is_valid_and_decodable(): void
    {
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        $decoded = $this->tokenService->decode($session['token']);

        $this->assertNotNull($decoded);
        $this->assertEquals('webchat_session', $decoded['sub']);
        $this->assertEquals($this->org->id, $decoded['org']);
        $this->assertEquals($this->channel->id, $decoded['chn']);
        $this->assertNull($decoded['cid']);
        $this->assertEquals($session['session_id'], $decoded['sid']);
    }

    public function test_expired_token_is_rejected(): void
    {
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        // Manually create expired token
        $decoded = $this->tokenService->decode($session['token']);
        $this->assertNotNull($decoded);

        // Travel forward 25 hours
        $this->travel(25)->hours();
        $decoded = $this->tokenService->decode($session['token']);
        $this->assertNull($decoded);
    }

    public function test_tampered_token_is_rejected(): void
    {
        $decoded = $this->tokenService->decode('tampered-invalid-token');
        $this->assertNull($decoded);
    }

    // --- Sending messages ---

    public function test_send_message_creates_conversation_and_message(): void
    {
        Event::fake();
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        $response = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => 'Hola, necesito info'],
            ['X-Webchat-Token' => $session['token']]
        );

        $response->assertCreated();
        $response->assertJsonStructure(['message_id', 'token']);

        $this->assertDatabaseHas('conversations', [
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'contact_identifier' => $session['session_id'],
            'contact_name' => 'Visitante Web',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('messages', [
            'organization_id' => $this->org->id,
            'body' => 'Hola, necesito info',
            'direction' => 'inbound',
            'type' => 'text',
        ]);
    }

    public function test_send_message_dispatches_events(): void
    {
        Event::fake([ConversationCreated::class, MessageReceivedEvent::class]);
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => 'Hello'],
            ['X-Webchat-Token' => $session['token']]
        );

        Event::assertDispatched(ConversationCreated::class);
        Event::assertDispatched(MessageReceivedEvent::class);
    }

    public function test_second_message_reuses_conversation(): void
    {
        Event::fake();
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        $r1 = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => 'First'],
            ['X-Webchat-Token' => $session['token']]
        );
        $r1->assertCreated();

        // Use updated token from first response
        $updatedToken = $r1->json('token');

        // Need to clear burst rate limit for test
        $this->travel(4)->seconds();

        $r2 = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => 'Second'],
            ['X-Webchat-Token' => $updatedToken]
        );
        $r2->assertCreated();

        $this->assertCount(
            1,
            Conversation::withoutGlobalScopes()->where('organization_id', $this->org->id)->get()
        );
        $this->assertCount(
            2,
            Message::withoutGlobalScopes()->where('organization_id', $this->org->id)->get()
        );
    }

    public function test_send_message_without_token_fails(): void
    {
        $response = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => 'Hello']
        );

        $response->assertUnauthorized();
    }

    public function test_send_message_with_invalid_token_fails(): void
    {
        $response = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => 'Hello'],
            ['X-Webchat-Token' => 'invalid-token']
        );

        $response->assertUnauthorized();
    }

    public function test_send_message_body_validation(): void
    {
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        // Empty body
        $response = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => ''],
            ['X-Webchat-Token' => $session['token']]
        );
        $response->assertUnprocessable();

        // Missing body
        $response = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            [],
            ['X-Webchat-Token' => $session['token']]
        );
        $response->assertUnprocessable();

        // Body too long
        $response = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => str_repeat('a', 2001)],
            ['X-Webchat-Token' => $session['token']]
        );
        $response->assertUnprocessable();
    }

    // --- Cross-tenant isolation ---

    public function test_token_from_org_a_cannot_be_used_on_org_b_channel(): void
    {
        $org2 = Organization::factory()->create();
        $channel2 = Channel::factory()->webchat()->create([
            'organization_id' => $org2->id,
        ]);

        // Create token for org1
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        // Try to use it on org2's channel
        $response = $this->postJson(
            "/api/webchat/{$channel2->uuid}/messages",
            ['body' => 'Cross-tenant attempt'],
            ['X-Webchat-Token' => $session['token']]
        );

        $response->assertForbidden();
    }

    // --- Get messages ---

    public function test_get_messages_returns_conversation_history(): void
    {
        Event::fake();
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        // Send a message to create conversation
        $r = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => 'Hello'],
            ['X-Webchat-Token' => $session['token']]
        );
        $updatedToken = $r->json('token');

        $response = $this->getJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['X-Webchat-Token' => $updatedToken]
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
    }

    public function test_get_messages_excludes_internal_notes(): void
    {
        Event::fake();
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        // Send message to create conversation
        $r = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => 'Hi'],
            ['X-Webchat-Token' => $session['token']]
        );
        $updatedToken = $r->json('token');
        $decoded = $this->tokenService->decode($updatedToken);

        // Create internal note directly
        Message::create([
            'organization_id' => $this->org->id,
            'conversation_id' => $decoded['cid'],
            'body' => 'Internal agent note',
            'type' => 'internal_note',
            'direction' => 'outbound',
        ]);

        $response = $this->getJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['X-Webchat-Token' => $updatedToken]
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
        $this->assertNotEquals('Internal agent note', $response->json('messages.0.body'));
    }

    // --- Broadcast auth for webchat ---

    public function test_webchat_broadcast_auth_succeeds_for_own_conversation(): void
    {
        Event::fake();
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        // Create conversation first
        $r = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => 'Init'],
            ['X-Webchat-Token' => $session['token']]
        );
        $updatedToken = $r->json('token');
        $decoded = $this->tokenService->decode($updatedToken);

        $response = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/broadcasting/auth",
            [
                'socket_id' => '12345.67890',
                'channel_name' => "private-conversation.{$this->org->id}.{$decoded['cid']}",
            ],
            ['X-Webchat-Token' => $updatedToken]
        );

        $response->assertOk();
        $response->assertJsonStructure(['auth']);
    }

    public function test_webchat_broadcast_auth_fails_for_other_conversation(): void
    {
        Event::fake();
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        $r = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/messages",
            ['body' => 'Init'],
            ['X-Webchat-Token' => $session['token']]
        );
        $updatedToken = $r->json('token');

        $response = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/broadcasting/auth",
            [
                'socket_id' => '12345.67890',
                'channel_name' => "private-conversation.{$this->org->id}.99999",
            ],
            ['X-Webchat-Token' => $updatedToken]
        );

        $response->assertForbidden();
    }

    public function test_webchat_broadcast_auth_fails_for_org_channel(): void
    {
        $session = $this->tokenService->create($this->org->id, $this->channel->id);

        $response = $this->postJson(
            "/api/webchat/{$this->channel->uuid}/broadcasting/auth",
            [
                'socket_id' => '12345.67890',
                'channel_name' => "private-organization.{$this->org->id}",
            ],
            ['X-Webchat-Token' => $session['token']]
        );

        $response->assertStatus(401); // No conversation in token yet
    }
}
