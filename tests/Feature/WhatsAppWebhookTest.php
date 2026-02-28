<?php

namespace Tests\Feature;

use App\Events\ConversationCreated;
use App\Events\MessageReceivedEvent;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Channel $channel;
    private string $appSecret;
    private string $verifyToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->appSecret = 'test-app-secret-123';
        $this->verifyToken = 'test-verify-token-456';
        $this->channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'type' => 'whatsapp',
            'configuration' => [
                'phone_number_id' => '100200300',
                'waba_id' => '900800700',
                'access_token' => 'EAAtest123',
                'verify_token' => $this->verifyToken,
                'app_secret' => $this->appSecret,
                'display_phone' => '+5215512340001',
            ],
        ]);
    }

    // --- Webhook verification (GET) ---

    public function test_verify_succeeds_with_correct_token(): void
    {
        $response = $this->get("/api/webhooks/whatsapp/{$this->channel->uuid}?" . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $this->verifyToken,
            'hub_challenge' => 'challenge-string-123',
        ]));

        $response->assertOk();
        $response->assertSee('challenge-string-123');
    }

    public function test_verify_fails_with_wrong_token(): void
    {
        $response = $this->get("/api/webhooks/whatsapp/{$this->channel->uuid}?" . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong-token',
            'hub_challenge' => 'challenge-string-123',
        ]));

        $response->assertForbidden();
    }

    public function test_verify_fails_with_unknown_channel(): void
    {
        $fakeUuid = (string) Str::uuid();

        $response = $this->get("/api/webhooks/whatsapp/{$fakeUuid}?" . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $this->verifyToken,
            'hub_challenge' => 'challenge-string-123',
        ]));

        $response->assertNotFound();
    }

    public function test_verify_fails_with_invalid_mode(): void
    {
        $response = $this->get("/api/webhooks/whatsapp/{$this->channel->uuid}?" . http_build_query([
            'hub_mode' => 'unsubscribe',
            'hub_verify_token' => $this->verifyToken,
            'hub_challenge' => 'challenge-string-123',
        ]));

        $response->assertForbidden();
    }

    public function test_verify_fails_for_inactive_channel(): void
    {
        $this->channel->update(['is_active' => false]);

        $response = $this->get("/api/webhooks/whatsapp/{$this->channel->uuid}?" . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $this->verifyToken,
            'hub_challenge' => 'challenge-string-123',
        ]));

        $response->assertNotFound();
    }

    // --- Webhook payload (POST) - Signature validation ---

    public function test_handle_rejects_missing_signature(): void
    {
        $payload = $this->buildWebhookPayload();

        $response = $this->postJson(
            "/api/webhooks/whatsapp/{$this->channel->uuid}",
            $payload,
        );

        $response->assertUnauthorized();
    }

    public function test_handle_rejects_invalid_signature(): void
    {
        $payload = $this->buildWebhookPayload();

        $response = $this->postJson(
            "/api/webhooks/whatsapp/{$this->channel->uuid}",
            $payload,
            ['X-Hub-Signature-256' => 'sha256=invalid']
        );

        $response->assertUnauthorized();
    }

    public function test_handle_accepts_valid_signature(): void
    {
        Event::fake();
        $payload = $this->buildWebhookPayload();

        $response = $this->postWithSignature($payload);

        $response->assertOk();
    }

    // --- Incoming message processing ---

    public function test_inbound_message_creates_conversation_and_message(): void
    {
        Event::fake();
        $payload = $this->buildWebhookPayload();

        $this->postWithSignature($payload);

        $this->assertDatabaseHas('conversations', [
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'contact_identifier' => '5215512345678',
            'contact_name' => 'Juan Pérez',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('messages', [
            'organization_id' => $this->org->id,
            'body' => 'Hola, necesito ayuda',
            'type' => 'text',
            'direction' => 'inbound',
            'external_id' => 'wamid.test123',
        ]);
    }

    public function test_inbound_message_dispatches_events(): void
    {
        Event::fake([ConversationCreated::class, MessageReceivedEvent::class]);
        $payload = $this->buildWebhookPayload();

        $this->postWithSignature($payload);

        Event::assertDispatched(ConversationCreated::class);
        Event::assertDispatched(MessageReceivedEvent::class);
    }

    public function test_second_message_reuses_existing_open_conversation(): void
    {
        Event::fake();

        $this->postWithSignature($this->buildWebhookPayload('wamid.first'));
        $this->postWithSignature($this->buildWebhookPayload('wamid.second', 'Segundo mensaje'));

        $this->assertCount(1, Conversation::withoutGlobalScopes()->where('organization_id', $this->org->id)->get());
        $this->assertCount(2, Message::withoutGlobalScopes()->where('organization_id', $this->org->id)->get());
    }

    public function test_duplicate_message_is_not_processed_twice(): void
    {
        Event::fake();

        $this->postWithSignature($this->buildWebhookPayload('wamid.same'));
        $this->postWithSignature($this->buildWebhookPayload('wamid.same'));

        $this->assertCount(1, Message::withoutGlobalScopes()->where('external_id', 'wamid.same')->get());
    }

    // --- Phone number ID validation ---

    public function test_rejects_payload_with_wrong_phone_number_id(): void
    {
        Event::fake();
        $payload = $this->buildWebhookPayload();
        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = '999999999';

        $this->postWithSignature($payload);

        $this->assertDatabaseMissing('messages', [
            'organization_id' => $this->org->id,
        ]);
    }

    // --- Multi-tenant isolation ---

    public function test_webhook_creates_entities_for_correct_organization(): void
    {
        Event::fake();
        $org2 = Organization::factory()->create();
        $channel2 = Channel::factory()->create([
            'organization_id' => $org2->id,
            'type' => 'whatsapp',
            'configuration' => [
                'phone_number_id' => '555666777',
                'waba_id' => '111222333',
                'access_token' => 'EAAother',
                'verify_token' => 'other-verify',
                'app_secret' => 'other-secret',
                'display_phone' => '+5215599990001',
            ],
        ]);

        // Send to channel2 with channel2's secret
        $payload = $this->buildWebhookPayload('wamid.org2msg', 'Mensaje org2', '555666777');
        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'other-secret');

        $this->postJson(
            "/api/webhooks/whatsapp/{$channel2->uuid}",
            $payload,
            ['X-Hub-Signature-256' => $signature]
        );

        // Message belongs to org2, not org1
        $message = Message::withoutGlobalScopes()->where('external_id', 'wamid.org2msg')->first();
        $this->assertNotNull($message);
        $this->assertEquals($org2->id, $message->organization_id);
        $this->assertEquals($channel2->id, $message->conversation->channel_id);
    }

    public function test_cannot_send_webhook_to_wrong_channel(): void
    {
        Event::fake();
        $org2 = Organization::factory()->create();
        $channel2 = Channel::factory()->create([
            'organization_id' => $org2->id,
            'type' => 'whatsapp',
            'configuration' => [
                'phone_number_id' => '555666777',
                'waba_id' => '111222333',
                'access_token' => 'EAAother',
                'verify_token' => 'other-verify',
                'app_secret' => 'other-app-secret',
                'display_phone' => '+5215599990001',
            ],
        ]);

        // Try to send to channel2 using org1's app_secret (forged)
        $payload = $this->buildWebhookPayload();
        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->postJson(
            "/api/webhooks/whatsapp/{$channel2->uuid}",
            $payload,
            ['X-Hub-Signature-256' => $signature]
        );

        $response->assertUnauthorized();
    }

    // --- Helpers ---

    private function buildWebhookPayload(
        string $messageId = 'wamid.test123',
        string $body = 'Hola, necesito ayuda',
        string $phoneNumberId = '100200300',
    ): array {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '900800700',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+5215512340001',
                                    'phone_number_id' => $phoneNumberId,
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Juan Pérez'],
                                        'wa_id' => '5215512345678',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '5215512345678',
                                        'id' => $messageId,
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => $body],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function postWithSignature(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);

        return $this->call(
            'POST',
            "/api/webhooks/whatsapp/{$this->channel->uuid}",
            [],
            [],
            [],
            ['HTTP_X_HUB_SIGNATURE_256' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body,
        );
    }
}
