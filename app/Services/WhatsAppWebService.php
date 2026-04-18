<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\WhatsAppWebSession;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class WhatsAppWebService
{
    public const CHANNEL_TYPE = 'whatsapp_web';
    private const PREFIX = 'wa';

    public function outboundChannel(int|string $channelId): string
    {
        return self::PREFIX . ':outbound:' . $channelId;
    }

    public function inboundPattern(): string
    {
        return self::PREFIX . ':inbound:*';
    }

    public function statusPattern(): string
    {
        return self::PREFIX . ':status:*';
    }

    public function extractChannelId(string $redisChannel): ?string
    {
        $parts = explode(':', $redisChannel);
        return $parts[2] ?? null;
    }

    public function requestPair(Channel $channel, bool $resetAuth = false): WhatsAppWebSession
    {
        $this->ensureWebChannel($channel);

        $session = $channel->whatsappWebSession()->firstOrCreate(
            ['channel_id' => $channel->id],
            ['status' => WhatsAppWebSession::STATUS_DISCONNECTED],
        );

        $session->update([
            'status' => WhatsAppWebSession::STATUS_QR_PENDING,
            'qr_raw' => null,
            'last_error' => null,
        ]);

        $this->publish($channel->id, [
            'type' => 'pair',
            'ref' => (string) Str::uuid(),
            'resetAuth' => $resetAuth,
        ]);

        return $session->refresh();
    }

    public function sendText(Channel $channel, string $to, string $text, ?string $ref = null): string
    {
        $this->ensureWebChannel($channel);

        $ref = $ref ?: (string) Str::uuid();

        $this->publish($channel->id, [
            'type' => 'send_text',
            'to' => $to,
            'text' => $text,
            'ref' => $ref,
        ]);

        return $ref;
    }

    public function logout(Channel $channel): void
    {
        $this->ensureWebChannel($channel);

        $this->publish($channel->id, [
            'type' => 'logout',
            'ref' => (string) Str::uuid(),
        ]);
    }

    public const REDIS_CONNECTION = 'wa_bridge';

    private function publish(int|string $channelId, array $payload): void
    {
        Redis::connection(self::REDIS_CONNECTION)
            ->publish($this->outboundChannel($channelId), json_encode($payload));
    }

    private function ensureWebChannel(Channel $channel): void
    {
        if ($channel->type !== self::CHANNEL_TYPE) {
            throw new \InvalidArgumentException(
                "Channel #{$channel->id} is not of type whatsapp_web (got: {$channel->type})"
            );
        }
    }
}
