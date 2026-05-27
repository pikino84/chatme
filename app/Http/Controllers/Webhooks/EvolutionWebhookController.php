<?php

namespace App\Http\Controllers\Webhooks;

use App\Events\ConversationCreated;
use App\Events\MessageReceivedEvent;
use App\Http\Controllers\Controller;
use App\Jobs\DownloadEvolutionMediaJob;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\WhatsAppWebSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recibe los webhooks de Evolution API para "WhatsApp Directo" (Phase 22).
 *
 * Sustituye al comando `wa:listen` (que escuchaba Redis pub/sub del bridge custom).
 * Eventos manejados: QRCODE_UPDATED, CONNECTION_UPDATE, MESSAGES_UPSERT, MESSAGES_UPDATE.
 */
class EvolutionWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Autenticación: token compartido en el header Authorization.
        $expected = (string) config('services.evolution.webhook_token');
        if ($expected !== '') {
            $given = (string) $request->header('authorization', '');
            if (! hash_equals($expected, $given)) {
                return response()->json(['error' => 'unauthorized'], 401);
            }
        }

        $event = strtolower(str_replace('_', '.', (string) $request->input('event')));
        $instance = (string) $request->input('instance');
        $data = $request->input('data', []);

        $channel = $this->resolveChannel($instance);
        if (! $channel) {
            // Instancia desconocida: 200 para que Evolution no reintente en bucle.
            Log::info('Evolution webhook: instancia desconocida', ['instance' => $instance, 'event' => $event]);
            return response()->json(['ignored' => true]);
        }

        try {
            match ($event) {
                'qrcode.updated' => $this->onQrUpdated($channel, $data),
                'connection.update' => $this->onConnectionUpdate($channel, $data),
                'messages.upsert' => $this->onMessagesUpsert($channel, $data),
                'messages.update' => $this->onMessagesUpdate($channel, $data),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Evolution webhook: handler falló', [
                'event' => $event,
                'instance' => $instance,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function resolveChannel(string $instance): ?Channel
    {
        if ($instance === '') {
            return null;
        }

        $session = WhatsAppWebSession::where('instance_name', $instance)->first();
        if (! $session) {
            return null;
        }

        return Channel::withoutGlobalScopes()->find($session->channel_id);
    }

    private function session(Channel $channel): WhatsAppWebSession
    {
        return WhatsAppWebSession::firstOrCreate(
            ['channel_id' => $channel->id],
            ['status' => WhatsAppWebSession::STATUS_DISCONNECTED],
        );
    }

    // --- Eventos de conexión ------------------------------------------------

    private function onQrUpdated(Channel $channel, array $data): void
    {
        $code = data_get($data, 'qrcode.code') ?? data_get($data, 'code');
        if (! $code) {
            return;
        }

        $this->session($channel)->update([
            'status' => WhatsAppWebSession::STATUS_QR_PENDING,
            'qr_raw' => $code,
            'qr_generated_at' => now(),
            'last_error' => null,
        ]);
    }

    private function onConnectionUpdate(Channel $channel, array $data): void
    {
        $state = (string) (data_get($data, 'state') ?? data_get($data, 'connection'));
        $session = $this->session($channel);

        match ($state) {
            'open' => $session->update([
                'status' => WhatsAppWebSession::STATUS_CONNECTED,
                'qr_raw' => null,
                'connected_phone' => $this->digits((string) data_get($data, 'wuid', $session->connected_phone ?? '')),
                'last_connected_at' => now(),
                'last_error' => null,
            ]),
            'connecting' => $session->update([
                'status' => WhatsAppWebSession::STATUS_CONNECTING,
            ]),
            'close' => $session->update([
                'status' => WhatsAppWebSession::STATUS_DISCONNECTED,
                'last_disconnected_at' => now(),
                'last_error' => data_get($data, 'statusReason') ? 'reason ' . data_get($data, 'statusReason') : null,
            ]),
            default => null,
        };
    }

    // --- Mensajes entrantes -------------------------------------------------

    private function onMessagesUpsert(Channel $channel, array $data): void
    {
        foreach ($this->messageList($data) as $msg) {
            if (! is_array($msg)) {
                continue;
            }
            $this->storeInboundMessage($channel, $msg);
        }
    }

    /** Evolution puede mandar un objeto suelto o una lista; normaliza a lista. */
    private function messageList(array $data): array
    {
        if ($data === []) {
            return [];
        }
        if (isset($data['messages']) && is_array($data['messages'])) {
            return $data['messages'];
        }
        if (array_is_list($data)) {
            return $data;
        }
        // Objeto asociativo suelto (un mensaje en upsert, una actualización en update).
        return [$data];
    }

    private function storeInboundMessage(Channel $channel, array $msg): void
    {
        $key = $msg['key'] ?? [];
        $remoteJid = (string) ($key['remoteJid'] ?? '');
        $msgId = $key['id'] ?? null;

        // fromMe = enviado por nosotros (desde la app O desde el teléfono vinculado).
        // Antes se descartaban; ahora los reflejamos como salientes (sync multi-dispositivo,
        // estilo WhatsApp Web). El dedup por external_id evita duplicar lo que ya mandó la app.
        $fromMe = ($key['fromMe'] ?? false) === true;

        if (! $msgId) {
            return;
        }

        // Ignorar grupos, estados, listas de difusión y canales.
        if ($remoteJid === '' || $remoteJid === 'status@broadcast'
            || str_ends_with($remoteJid, '@g.us')
            || str_ends_with($remoteJid, '@broadcast')
            || str_ends_with($remoteJid, '@newsletter')) {
            return;
        }

        $phone = $this->digits(explode('@', $remoteJid)[0]);
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            return;
        }

        $direction = $fromMe ? 'outbound' : 'inbound';
        $content = $this->extractContent($msg['message'] ?? [], (string) ($msg['messageType'] ?? ''));

        // En un fromMe el pushName es el NUESTRO, no el del contacto: no lo usamos para nombrar.
        $contactName = $fromMe ? $phone : ($msg['pushName'] ?? $phone);
        $conversation = $this->findOrCreateConversation($channel, $phone, $contactName);

        // Idempotente por external_id: si el mensaje ya existe (lo creó la app al enviar,
        // o es un webhook repetido) NO se duplica. wasRecentlyCreated distingue el alta real.
        $message = Message::withoutGlobalScopes()->firstOrCreate(
            [
                'organization_id' => $channel->organization_id,
                'external_id' => $msgId,
            ],
            [
                'conversation_id' => $conversation->id,
                'body' => $content['body'],
                'type' => $content['type'],
                'direction' => $direction,
                'metadata' => [
                    'wa_web' => true,
                    'wa_message_id' => $msgId,
                    'wa_timestamp' => $msg['messageTimestamp'] ?? null,
                    'media_kind' => $content['media']['kind'] ?? null,
                    'from_phone' => $fromMe, // salió del teléfono, no de la app
                ],
            ]
        );

        if (! $message->wasRecentlyCreated) {
            return; // ya estaba registrado (envío de la app o webhook repetido)
        }

        // Si trae multimedia, crear el adjunto y encolar la descarga a S3.
        if ($content['media']) {
            $attachment = MessageAttachment::create([
                'organization_id' => $channel->organization_id,
                'message_id' => $message->id,
                'file_name' => $content['media']['file_name'],
                'file_path' => '', // lo rellena DownloadEvolutionMediaJob al subir a S3
                'mime_type' => $content['media']['mime'] ?: 'application/octet-stream',
                'media_type' => $content['media']['kind'],
                'width' => $content['media']['width'],
                'height' => $content['media']['height'],
                'duration' => $content['media']['duration'],
                'status' => 'pending',
            ]);

            DownloadEvolutionMediaJob::dispatch($message->id, $attachment->id, $channel->id, $key);
        }

        // El contador de no leídos sólo sube con ENTRANTES. Un saliente (incluido lo
        // enviado desde el teléfono) actualiza el orden pero no marca como no leído.
        $convUpdate = ['last_message_at' => now()];
        if (! $fromMe) {
            $convUpdate['unread_count'] = $conversation->unread_count + 1;
        }
        $conversation->update($convUpdate);

        // Sólo los entrantes disparan notificación/sonido de "mensaje nuevo".
        if (! $fromMe) {
            $this->safeDispatch(fn () => MessageReceivedEvent::dispatch($message), 'MessageReceivedEvent');
        }
    }

    private function onMessagesUpdate(Channel $channel, array $data): void
    {
        foreach ($this->messageList($data) as $upd) {
            if (! is_array($upd)) {
                continue;
            }
            $msgId = $upd['keyId'] ?? data_get($upd, 'key.id') ?? ($upd['messageId'] ?? null);
            $status = $upd['status'] ?? data_get($upd, 'update.status');
            if (! $msgId || $status === null) {
                continue;
            }

            $message = Message::withoutGlobalScopes()
                ->where('organization_id', $channel->organization_id)
                ->where('external_id', $msgId)
                ->first();
            if (! $message) {
                continue;
            }

            $message->update([
                'metadata' => array_merge($message->metadata ?? [], [
                    'wa_ack_status' => $status,
                    'wa_ack_at' => now()->toISOString(),
                ]),
            ]);
        }
    }

    // --- Helpers ------------------------------------------------------------

    /**
     * Extrae texto/tipo/multimedia del nodo `message` de Evolution.
     *
     * @return array{type:string, body:string, media:?array}
     */
    private function extractContent(array $message, string $messageType): array
    {
        // documentWithCaptionMessage envuelve un documentMessage real.
        if (isset($message['documentWithCaptionMessage'])) {
            $message = $message['documentWithCaptionMessage']['message'] ?? $message;
            $messageType = 'documentMessage';
        }

        // Texto plano.
        if (isset($message['conversation'])) {
            return ['type' => 'text', 'body' => (string) $message['conversation'], 'media' => null];
        }
        if (isset($message['extendedTextMessage']['text'])) {
            return ['type' => 'text', 'body' => (string) $message['extendedTextMessage']['text'], 'media' => null];
        }

        $mediaMap = [
            'imageMessage' => 'image',
            'videoMessage' => 'video',
            'audioMessage' => 'audio',
            'documentMessage' => 'document',
            'stickerMessage' => 'sticker',
        ];

        foreach ($mediaMap as $node => $kind) {
            if (! isset($message[$node])) {
                continue;
            }
            $m = $message[$node];

            return [
                'type' => match ($kind) {
                    'image', 'sticker' => 'image',
                    'video' => 'video',
                    'audio' => 'audio',
                    'document' => 'file',
                },
                'body' => (string) ($m['caption'] ?? $this->mediaPlaceholder($kind)),
                'media' => [
                    'kind' => $kind,
                    'mime' => $m['mimetype'] ?? null,
                    'file_name' => $m['fileName'] ?? ($kind . '_' . now()->timestamp),
                    'width' => $m['width'] ?? null,
                    'height' => $m['height'] ?? null,
                    'duration' => $m['seconds'] ?? null,
                ],
            ];
        }

        // Ubicación, contacto u otros: solo un marcador de texto.
        return match (true) {
            isset($message['locationMessage']) => ['type' => 'text', 'body' => '[Ubicación]', 'media' => null],
            isset($message['contactMessage']),
            isset($message['contactsArrayMessage']) => ['type' => 'text', 'body' => '[Contacto]', 'media' => null],
            default => ['type' => 'text', 'body' => '', 'media' => null],
        };
    }

    private function mediaPlaceholder(string $kind): string
    {
        return match ($kind) {
            'image' => '[Imagen]',
            'video' => '[Video]',
            'audio' => '[Audio]',
            'document' => '[Documento]',
            'sticker' => '[Sticker]',
            default => '',
        };
    }

    private function findOrCreateConversation(Channel $channel, string $phone, string $contactName): Conversation
    {
        $conversation = Conversation::withoutGlobalScopes()
            ->where('organization_id', $channel->organization_id)
            ->where('channel_id', $channel->id)
            ->where('contact_identifier', $phone)
            ->where('status', '!=', 'closed')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $conversation = Conversation::create([
            'organization_id' => $channel->organization_id,
            'brand_id' => $channel->brand_id,
            'channel_id' => $channel->id,
            'contact_name' => $contactName,
            'contact_identifier' => $phone,
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => now(),
        ]);

        $this->safeDispatch(fn () => ConversationCreated::dispatch($conversation), 'ConversationCreated');

        return $conversation;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    /** Los broadcasts no deben abortar la persistencia si Reverb está caído. */
    private function safeDispatch(callable $fn, string $label): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning("Evolution webhook: broadcast falló ({$label}) — ignorado", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
