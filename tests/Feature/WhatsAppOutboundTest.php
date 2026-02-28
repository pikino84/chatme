<?php

namespace Tests\Feature;

use App\Events\MessageSentEvent;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Services\WhatsAppService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppOutboundTest extends TestCase
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
        $this->channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'type' => 'whatsapp',
            'configuration' => [
                'phone_number_id' => '100200300',
                'waba_id' => '900800700',
                'access_token' => 'EAAtest123',
                'verify_token' => 'test-verify',
                'app_secret' => 'test-secret',
                'display_phone' => '+5215512340001',
            ],
        ]);
        $this->conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'contact_identifier' => '5215512345678',
        ]);
    }

    public function test_send_whatsapp_message_job_calls_api(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['wa_id' => '5215512345678']],
                'messages' => [['id' => 'wamid.response123']],
            ]),
        ]);

        Event::fake([MessageSentEvent::class]);

        $message = Message::factory()->outbound()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'body' => 'Respuesta del agente',
        ]);

        $job = new SendWhatsAppMessage($message);
        $job->handle(app(WhatsAppService::class));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '100200300/messages')
                && $request['to'] === '5215512345678'
                && $request['text']['body'] === 'Respuesta del agente';
        });

        $message->refresh();
        $this->assertEquals('wamid.response123', $message->external_id);

        Event::assertDispatched(MessageSentEvent::class);
    }

    public function test_send_whatsapp_message_handles_api_failure(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => 'Rate limit'], 429),
        ]);

        Event::fake([MessageSentEvent::class]);

        $message = Message::factory()->outbound()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'body' => 'Test message',
        ]);

        $job = new SendWhatsAppMessage($message);
        $job->handle(app(WhatsAppService::class));

        $message->refresh();
        $this->assertNull($message->external_id);

        Event::assertNotDispatched(MessageSentEvent::class);
    }

    public function test_send_job_is_queueable(): void
    {
        Queue::fake();

        $message = Message::factory()->outbound()->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'body' => 'Queued message',
        ]);

        SendWhatsAppMessage::dispatch($message);

        Queue::assertPushed(SendWhatsAppMessage::class, function ($job) use ($message) {
            return $job->message->id === $message->id;
        });
    }

    public function test_whatsapp_service_sends_to_correct_endpoint(): void
    {
        Http::fake([
            'graph.facebook.com/v21.0/100200300/messages' => Http::response([
                'messages' => [['id' => 'wamid.ok']],
            ]),
        ]);

        $service = app(WhatsAppService::class);
        $result = $service->sendTextMessage($this->channel, '5215512345678', 'Hola');

        $this->assertNotNull($result);
        Http::assertSentCount(1);
    }
}
