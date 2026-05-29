<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use App\Models\WhatsAppWebSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Importador de historial de WhatsApp Business (Fase 22.6).
 */
class WhatsAppDirectoImportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Channel $channel;
    private User $user;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        Storage::fake('local');
        Storage::fake('chatme-media');

        $this->org = Organization::factory()->create();
        $this->channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'type' => 'whatsapp_web',
        ]);
        WhatsAppWebSession::create([
            'channel_id' => $this->channel->id,
            'instance_name' => 'chatme-ch-import',
            'status' => WhatsAppWebSession::STATUS_CONNECTED,
            'connected_name' => 'Ventas MX',
        ]);

        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->user->assignRole('supervisor');

        $this->domain = 'http://app.' . config('app.base_domain');
        app()->instance('tenant', $this->org);

        Event::fake();
    }

    private function url(string $path): string
    {
        return "{$this->domain}/whatsapp-directo/{$this->channel->id}{$path}";
    }

    private function makeChatZip(): UploadedFile
    {
        $chat = "12/04/2023, 14:32 - Juan Pérez: Hola\n"
            . "12/04/2023, 14:33 - Ventas MX: Buenas, ¿en qué ayudo?\n"
            . "12/04/2023, 14:34 - Juan Pérez: IMG-1.jpg (archivo adjunto)\n";

        $zipPath = tempnam(sys_get_temp_dir(), 'wachat') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('_chat.txt', $chat);
        $zip->addFromString('IMG-1.jpg', random_bytes(256));
        $zip->close();

        return new UploadedFile($zipPath, 'WhatsApp Chat.zip', 'application/zip', null, true);
    }

    public function test_analyze_returns_summary_and_token(): void
    {
        $res = $this->actingAs($this->user)
            ->post($this->url('/import/analyze'), [
                'file' => $this->makeChatZip(),
                'day_first' => '1',
            ]);

        $res->assertOk()
            ->assertJsonStructure(['token', 'summary' => ['total', 'senders', 'media_count', 'first_ts', 'last_ts'], 'suggested_me'])
            ->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.media_count', 1)
            ->assertJsonPath('suggested_me', 'Ventas MX');

        $this->assertSame(2, $res->json('summary.senders.Juan Pérez'));
    }

    public function test_import_creates_messages_with_correct_direction_and_media(): void
    {
        $token = $this->actingAs($this->user)
            ->post($this->url('/import/analyze'), ['file' => $this->makeChatZip(), 'day_first' => '1'])
            ->json('token');

        $res = $this->actingAs($this->user)
            ->postJson($this->url('/import'), [
                'token' => $token,
                'me_sender' => 'Ventas MX',
                'day_first' => 1,
                'phone' => '5219981112233',
                'contact_name' => 'Juan Pérez',
            ]);

        $res->assertOk()
            ->assertJsonPath('imported', 3)
            ->assertJsonPath('media_imported', 1);

        $conversation = Conversation::where('channel_id', $this->channel->id)
            ->where('contact_identifier', '5219981112233')
            ->firstOrFail();

        $messages = Message::where('conversation_id', $conversation->id)->orderBy('id')->get();
        $this->assertCount(3, $messages);
        $this->assertSame('inbound', $messages[0]->direction);
        $this->assertSame('outbound', $messages[1]->direction); // "Ventas MX" = yo
        $this->assertTrue((bool) $messages[1]->metadata['from_phone']);

        // created_at toma la fecha original del chat.
        $this->assertSame('2023-04-12 14:32:00', $messages[0]->created_at->format('Y-m-d H:i:s'));

        // El mensaje con multimedia tiene un adjunto listo.
        $withMedia = $messages[2];
        $this->assertSame('image', $withMedia->type);
        $this->assertCount(1, $withMedia->attachments);
        $this->assertSame('ready', $withMedia->attachments->first()->status);
    }

    public function test_reimport_is_idempotent(): void
    {
        $payload = function () {
            $token = $this->actingAs($this->user)
                ->post($this->url('/import/analyze'), ['file' => $this->makeChatZip(), 'day_first' => '1'])
                ->json('token');

            return $this->actingAs($this->user)->postJson($this->url('/import'), [
                'token' => $token,
                'me_sender' => 'Ventas MX',
                'day_first' => 1,
                'phone' => '5219981112233',
                'contact_name' => 'Juan Pérez',
            ]);
        };

        $payload()->assertJsonPath('imported', 3);
        // Segunda corrida: todo ya existe (mismo external_id), nada se duplica.
        $payload()->assertJsonPath('imported', 0)->assertJsonPath('skipped', 3);

        $this->assertSame(3, Message::count());
    }

    public function test_import_into_existing_conversation(): void
    {
        $conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'contact_identifier' => '5210000000000',
        ]);

        $token = $this->actingAs($this->user)
            ->post($this->url('/import/analyze'), ['file' => $this->makeChatZip(), 'day_first' => '1'])
            ->json('token');

        $this->actingAs($this->user)
            ->postJson($this->url('/import'), [
                'token' => $token,
                'me_sender' => 'Ventas MX',
                'conversation_id' => $conversation->id,
            ])
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversation->id);

        $this->assertSame(3, Message::where('conversation_id', $conversation->id)->count());
    }
}
