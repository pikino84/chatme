<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\WhatsAppWebSession;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de Evolution API para "WhatsApp Directo" (Phase 22).
 *
 * Cada Channel de tipo 'whatsapp_web' corresponde a una "instancia" de Evolution.
 * Antes este servicio publicaba a Redis hacia el bridge Baileys custom; ahora habla
 * por HTTP con Evolution. Los eventos entrantes llegan por webhook
 * (App\Http\Controllers\Webhooks\EvolutionWebhookController), no por pub/sub.
 */
class WhatsAppWebService
{
    public const CHANNEL_TYPE = 'whatsapp_web';

    /** Eventos de Evolution a los que se suscribe cada instancia. */
    private const WEBHOOK_EVENTS = [
        'QRCODE_UPDATED',
        'CONNECTION_UPDATE',
        'MESSAGES_UPSERT',
        'MESSAGES_UPDATE',
    ];

    /** Nombre determinístico de la instancia de Evolution para un canal. */
    public function instanceName(Channel $channel): string
    {
        return 'chatme-ch-' . $channel->id;
    }

    /**
     * Solicita (o renueva) el QR de vinculación.
     *
     * @param bool $resetAuth Si true, destruye la instancia y la recrea desde cero.
     */
    public function requestPair(Channel $channel, bool $resetAuth = false): WhatsAppWebSession
    {
        $this->ensureWebChannel($channel);

        $session = $channel->whatsappWebSession()->firstOrCreate(
            ['channel_id' => $channel->id],
            ['status' => WhatsAppWebSession::STATUS_DISCONNECTED],
        );

        if ($resetAuth && $session->instance_name) {
            $this->safe(fn () => $this->deleteInstance($channel));
            $session->update(['instance_id' => null]);
        }

        // Sin instancia previa (o tras reset) → crear; si ya existe → reconectar.
        if ($resetAuth || ! $session->instance_id) {
            $qr = $this->createInstance($channel, $session);
        } else {
            try {
                $qr = $this->connect($channel, $session);
            } catch (\Throwable $e) {
                $qr = $this->createInstance($channel, $session);
            }
        }

        // No sobreescribir qr_raw con null: el webhook QRCODE_UPDATED puede haber
        // llegado ya (createInstance/connect tardan ~1-2s, igual que el QR).
        $update = [
            'status' => WhatsAppWebSession::STATUS_QR_PENDING,
            'qr_generated_at' => now(),
            'last_error' => null,
        ];
        if (! empty($qr['code'])) {
            $update['qr_raw'] = $qr['code'];
        }
        $session->update($update);

        return $session->refresh();
    }

    /**
     * Envía un mensaje de texto. Devuelve el id del mensaje de WhatsApp (key.id),
     * útil para deduplicar y casar los acks de entrega/lectura.
     */
    public function sendText(Channel $channel, string $to, string $text): string
    {
        $this->ensureWebChannel($channel);

        $resp = $this->http()->post('/message/sendText/' . $this->instanceFor($channel), [
            'number' => $this->normalizeNumber($to),
            'text' => $text,
        ]);

        if ($resp->failed()) {
            throw new \RuntimeException('Evolution sendText falló: ' . $resp->body());
        }

        return (string) (data_get($resp->json(), 'key.id') ?? '');
    }

    /**
     * Envía un archivo multimedia (imagen, video, audio o documento).
     * $mediaType: 'image' | 'video' | 'audio' | 'document'
     */
    public function sendMedia(
        Channel $channel,
        string $to,
        string $mediaType,
        string $mediaUrl,
        ?string $caption = null,
        ?string $fileName = null,
    ): string {
        $this->ensureWebChannel($channel);

        $resp = $this->http()->post('/message/sendMedia/' . $this->instanceFor($channel), array_filter([
            'number' => $this->normalizeNumber($to),
            'mediatype' => $mediaType,
            'media' => $mediaUrl,
            'caption' => $caption,
            'fileName' => $fileName,
        ], fn ($v) => $v !== null));

        if ($resp->failed()) {
            throw new \RuntimeException('Evolution sendMedia falló: ' . $resp->body());
        }

        return (string) (data_get($resp->json(), 'key.id') ?? '');
    }

    /** Descarga el binario de un mensaje multimedia como base64 (data crudo). */
    public function fetchMediaBase64(Channel $channel, array $messageKey): ?string
    {
        $resp = $this->http()->post('/chat/getBase64FromMediaMessage/' . $this->instanceFor($channel), [
            'message' => ['key' => $messageKey],
            'convertToMp4' => false,
        ]);

        if ($resp->failed()) {
            Log::warning('Evolution: getBase64FromMediaMessage falló', ['body' => $resp->body()]);
            return null;
        }

        return data_get($resp->json(), 'base64');
    }

    /** Cierra la sesión de WhatsApp (logout) sin destruir la instancia. */
    public function logout(Channel $channel): void
    {
        $this->ensureWebChannel($channel);
        $this->http()->delete('/instance/logout/' . $this->instanceFor($channel));
    }

    /** Destruye por completo la instancia en Evolution. */
    public function deleteInstance(Channel $channel): void
    {
        $name = $channel->whatsappWebSession?->instance_name ?: $this->instanceName($channel);
        $this->http()->delete('/instance/delete/' . $name);
    }

    /** Estado de conexión reportado por Evolution: open | connecting | close. */
    public function connectionState(Channel $channel): ?string
    {
        $resp = $this->http()->get('/instance/connectionState/' . $this->instanceFor($channel));

        return $resp->successful()
            ? data_get($resp->json(), 'instance.state')
            : null;
    }

    // ----------------------------------------------------------------------

    /** Crea la instancia en Evolution con el webhook ya configurado. */
    private function createInstance(Channel $channel, WhatsAppWebSession $session): array
    {
        $name = $this->instanceName($channel);

        $resp = $this->http()->post('/instance/create', [
            'instanceName' => $name,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
            'webhook' => [
                'url' => (string) config('services.evolution.webhook_url'),
                'byEvents' => false,
                'base64' => false,
                'headers' => [
                    'authorization' => (string) config('services.evolution.webhook_token'),
                    'Content-Type' => 'application/json',
                ],
                'events' => self::WEBHOOK_EVENTS,
            ],
        ]);

        // La instancia ya existía → simplemente reconectar.
        if (in_array($resp->status(), [403, 409], true)) {
            return $this->connect($channel, $session);
        }

        if ($resp->failed()) {
            $session->update(['last_error' => 'crear instancia: ' . $resp->body()]);
            throw new \RuntimeException('Evolution createInstance falló: ' . $resp->body());
        }

        $body = $resp->json();
        $hash = $body['hash'] ?? null;

        $session->update([
            'instance_name' => $name,
            'instance_id' => data_get($body, 'instance.instanceId'),
            'instance_apikey' => is_array($hash) ? ($hash['apikey'] ?? null) : $hash,
        ]);

        return [
            'code' => data_get($body, 'qrcode.code'),
            'base64' => data_get($body, 'qrcode.base64'),
        ];
    }

    /** Reconecta una instancia existente y obtiene un QR nuevo. */
    private function connect(Channel $channel, WhatsAppWebSession $session): array
    {
        $name = $session->instance_name ?: $this->instanceName($channel);
        $resp = $this->http()->get('/instance/connect/' . $name);

        if ($resp->failed()) {
            throw new \RuntimeException('Evolution connect falló: ' . $resp->body());
        }

        $body = $resp->json();

        return [
            'code' => $body['code'] ?? data_get($body, 'qrcode.code'),
            'base64' => $body['base64'] ?? data_get($body, 'qrcode.base64'),
        ];
    }

    private function instanceFor(Channel $channel): string
    {
        return $channel->whatsappWebSession?->instance_name ?: $this->instanceName($channel);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.evolution.base_url'), '/'))
            ->withHeaders(['apikey' => (string) config('services.evolution.api_key')])
            ->acceptJson()
            ->timeout(20);
    }

    /** Normaliza un teléfono a solo dígitos (Evolution no acepta el prefijo «+»). */
    private function normalizeNumber(string $to): string
    {
        return preg_replace('/\D/', '', $to);
    }

    private function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('WhatsAppWebService: operación ignorada', ['error' => $e->getMessage()]);
        }
    }

    private function ensureWebChannel(Channel $channel): void
    {
        if ($channel->type !== self::CHANNEL_TYPE) {
            throw new \InvalidArgumentException(
                "Channel #{$channel->id} no es de tipo whatsapp_web (es: {$channel->type})"
            );
        }
    }
}
