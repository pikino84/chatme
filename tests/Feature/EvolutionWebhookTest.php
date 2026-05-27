<?php

namespace Tests\Feature;

use App\Events\ConversationCreated;
use App\Events\MessageReceivedEvent;
use App\Jobs\DownloadEvolutionMediaJob;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\WhatsAppWebSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Webhook de Evolution API para "WhatsApp Directo" (Phase 22).
 */
class EvolutionWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Channel $channel;
    private string $instance = 'chatme-ch-test';
    private string $token = 'evo-webhook-token-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['services.evolution.webhook_token' => $this->token]);

        $this->org = Organization::factory()->create();
        $this->channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'type' => 'whatsapp_web',
        ]);
        WhatsAppWebSession::create([
            'channel_id' => $this->channel->id,
            'instance_name' => $this->instance,
            'status' => WhatsAppWebSession::STATUS_DISCONNECTED,
        ]);
    }

    // --- Autenticación por token ------------------------------------------

    public function test_rejects_missing_token(): void
    {
        $this->postJson('/api/webhooks/evolution', $this->qrPayload())
            ->assertUnauthorized();
    }

    public function test_rejects_wrong_token(): void
    {
        $this->postJson('/api/webhooks/evolution', $this->qrPayload(), [
            'Authorization' => 'nope',
        ])->assertUnauthorized();
    }

    public function test_unknown_instance_is_ignored(): void
    {
        $payload = $this->qrPayload();
        $payload['instance'] = 'chatme-ch-does-not-exist';

        $this->post(...$this->signed($payload))
            ->assertOk()
            ->assertJson(['ignored' => true]);
    }

    // --- QR y conexión ----------------------------------------------------

    public function test_qrcode_updated_stores_qr(): void
    {
        $this->post(...$this->signed($this->qrPayload()))->assertOk();

        $session = $this->channel->whatsappWebSession()->first();
        $this->assertSame(WhatsAppWebSession::STATUS_QR_PENDING, $session->status);
        $this->assertSame('2@QRCODESTRING', $session->qr_raw);
    }

    public function test_connection_update_open_marks_connected(): void
    {
        $this->post(...$this->signed([
            'event' => 'connection.update',
            'instance' => $this->instance,
            'data' => ['instance' => $this->instance, 'state' => 'open', 'statusReason' => 200],
        ]))->assertOk();

        $session = $this->channel->whatsappWebSession()->first();
        $this->assertSame(WhatsAppWebSession::STATUS_CONNECTED, $session->status);
        $this->assertNotNull($session->last_connected_at);
    }

    public function test_connection_update_close_marks_disconnected(): void
    {
        $this->post(...$this->signed([
            'event' => 'connection.update',
            'instance' => $this->instance,
            'data' => ['instance' => $this->instance, 'state' => 'close', 'statusReason' => 401],
        ]))->assertOk();

        $this->assertSame(
            WhatsAppWebSession::STATUS_DISCONNECTED,
            $this->channel->whatsappWebSession()->first()->status,
        );
    }

    // --- Mensajes entrantes ----------------------------------------------

    public function test_inbound_message_creates_conversation_and_message(): void
    {
        Event::fake([ConversationCreated::class, MessageReceivedEvent::class]);

        $this->post(...$this->signed($this->messagePayload()))->assertOk();

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
            'external_id' => 'WAMID-1',
        ]);

        Event::assertDispatched(ConversationCreated::class);
        Event::assertDispatched(MessageReceivedEvent::class);
    }

    public function test_duplicate_message_is_not_processed_twice(): void
    {
        Event::fake();

        $this->post(...$this->signed($this->messagePayload('WAMID-DUP')))->assertOk();
        $this->post(...$this->signed($this->messagePayload('WAMID-DUP')))->assertOk();

        $this->assertCount(1, Message::withoutGlobalScopes()->where('external_id', 'WAMID-DUP')->get());
    }

    public function test_own_outbound_from_phone_is_stored_as_outbound(): void
    {
        Event::fake();
        $payload = $this->messagePayload('WAMID-MINE');
        $payload['data']['key']['fromMe'] = true;

        $this->post(...$this->signed($payload))->assertOk();

        // Sync multi-dispositivo: lo enviado desde el teléfono se refleja como saliente.
        $this->assertDatabaseHas('messages', [
            'organization_id' => $this->org->id,
            'external_id' => 'WAMID-MINE',
            'direction' => 'outbound',
        ]);
        // Pero un saliente no notifica como "mensaje nuevo".
        Event::assertNotDispatched(MessageReceivedEvent::class);
    }

    public function test_own_outbound_from_app_is_not_duplicated(): void
    {
        Event::fake();

        // La app ya registró el saliente con su external_id al enviar.
        $conversation = Conversation::create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'contact_identifier' => '5215512345678',
            'contact_name' => '5215512345678',
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => now(),
        ]);
        Message::create([
            'organization_id' => $this->org->id,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'desde la app',
            'external_id' => 'WAMID-APP',
        ]);

        // Llega el eco fromMe de Evolution para ese mismo mensaje.
        $payload = $this->messagePayload('WAMID-APP');
        $payload['data']['key']['fromMe'] = true;
        $this->post(...$this->signed($payload))->assertOk();

        // No se duplica (dedup por external_id).
        $this->assertCount(1, Message::withoutGlobalScopes()->where('external_id', 'WAMID-APP')->get());
    }

    public function test_group_message_is_ignored(): void
    {
        Event::fake();
        $payload = $this->messagePayload('WAMID-GROUP');
        $payload['data']['key']['remoteJid'] = '120363000000000000@g.us';

        $this->post(...$this->signed($payload))->assertOk();

        $this->assertDatabaseMissing('messages', ['external_id' => 'WAMID-GROUP']);
    }

    public function test_inbound_media_message_creates_attachment_and_queues_download(): void
    {
        Event::fake();
        Queue::fake();

        $payload = $this->messagePayload('WAMID-IMG');
        $payload['data']['messageType'] = 'imageMessage';
        $payload['data']['message'] = [
            'imageMessage' => [
                'mimetype' => 'image/jpeg',
                'caption' => 'mira esto',
                'width' => 640,
                'height' => 480,
            ],
        ];

        $this->post(...$this->signed($payload))->assertOk();

        $message = Message::withoutGlobalScopes()->where('external_id', 'WAMID-IMG')->firstOrFail();
        $this->assertSame('image', $message->type);
        $this->assertSame('mira esto', $message->body);
        $this->assertDatabaseHas('message_attachments', [
            'message_id' => $message->id,
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
            'status' => 'pending',
        ]);
        Queue::assertPushed(DownloadEvolutionMediaJob::class);
    }

    public function test_messages_update_records_ack(): void
    {
        Event::fake();
        $this->post(...$this->signed($this->messagePayload('WAMID-ACK')))->assertOk();

        $this->post(...$this->signed([
            'event' => 'messages.update',
            'instance' => $this->instance,
            'data' => ['keyId' => 'WAMID-ACK', 'status' => 'READ', 'remoteJid' => '5215512345678@s.whatsapp.net'],
        ]))->assertOk();

        $message = Message::withoutGlobalScopes()->where('external_id', 'WAMID-ACK')->firstOrFail();
        $this->assertSame('READ', $message->metadata['wa_ack_status'] ?? null);
    }

    // --- Helpers ----------------------------------------------------------

    private function qrPayload(): array
    {
        return [
            'event' => 'qrcode.updated',
            'instance' => $this->instance,
            'data' => ['qrcode' => ['code' => '2@QRCODESTRING', 'base64' => 'data:image/png;base64,AAA']],
        ];
    }

    private function messagePayload(string $id = 'WAMID-1', string $body = 'Hola, necesito ayuda'): array
    {
        return [
            'event' => 'messages.upsert',
            'instance' => $this->instance,
            'data' => [
                'key' => [
                    'remoteJid' => '5215512345678@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => $id,
                ],
                'pushName' => 'Juan Pérez',
                'message' => ['conversation' => $body],
                'messageType' => 'conversation',
                'messageTimestamp' => time(),
            ],
        ];
    }

    /**
     * Devuelve los argumentos para $this->post(...) con el header de token.
     *
     * @return array{0:string,1:array,2:array}
     */
    private function signed(array $payload): array
    {
        return ['/api/webhooks/evolution', $payload, ['Authorization' => $this->token]];
    }
}
