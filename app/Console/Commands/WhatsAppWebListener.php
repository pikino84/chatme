<?php

namespace App\Console\Commands;

use App\Events\ConversationCreated;
use App\Events\MessageReceivedEvent;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppWebSession;
use App\Services\WhatsAppWebService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WhatsAppWebListener extends Command
{
    protected $signature = 'wa:listen';
    protected $description = 'Subscribe to Redis pub/sub from chatme-wa-bridge and persist inbound messages/status';

    public function handle(WhatsAppWebService $service): int
    {
        $this->info('Subscribing to wa:inbound:* and wa:status:*');

        Redis::connection(WhatsAppWebService::REDIS_CONNECTION)
            ->psubscribe([$service->inboundPattern(), $service->statusPattern()], function ($message, $channel) use ($service) {
            $channelId = $service->extractChannelId($channel);
            if (!$channelId) {
                return;
            }

            $payload = json_decode($message, true);
            if (!is_array($payload)) {
                Log::warning('wa:listen: invalid JSON', ['raw' => $message, 'channel' => $channel]);
                return;
            }

            try {
                if (str_contains($channel, ':inbound:')) {
                    $this->handleInbound((int) $channelId, $payload);
                } elseif (str_contains($channel, ':status:')) {
                    $this->handleStatus((int) $channelId, $payload);
                }
            } catch (\Throwable $e) {
                Log::error('wa:listen: handler crashed', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                    'payload' => $payload,
                ]);
            }
        });

        return self::SUCCESS;
    }

    private function handleInbound(int $channelId, array $payload): void
    {
        $channel = Channel::withoutGlobalScopes()->find($channelId);
        if (!$channel) {
            Log::warning('wa:listen: channel not found', ['channel_id' => $channelId]);
            return;
        }

        match ($payload['event'] ?? null) {
            'message' => $this->storeInboundMessage($channel, $payload),
            'ack' => $this->updateAck($channel, $payload),
            default => Log::info('wa:listen: unknown inbound event', ['payload' => $payload]),
        };
    }

    private function handleStatus(int $channelId, array $payload): void
    {
        $channel = Channel::withoutGlobalScopes()->find($channelId);
        if (!$channel) {
            return;
        }

        $session = WhatsAppWebSession::firstOrCreate(
            ['channel_id' => $channel->id],
            ['status' => WhatsAppWebSession::STATUS_DISCONNECTED],
        );

        $update = match ($payload['event'] ?? null) {
            'qr' => [
                'status' => WhatsAppWebSession::STATUS_QR_PENDING,
                'qr_raw' => $payload['qr'] ?? null,
                'qr_generated_at' => now(),
                'last_error' => null,
            ],
            'connected' => [
                'status' => WhatsAppWebSession::STATUS_CONNECTED,
                'qr_raw' => null,
                'connected_phone' => $payload['me'] ?? null,
                'connected_name' => $payload['name'] ?? null,
                'last_connected_at' => now(),
                'last_error' => null,
            ],
            'disconnected' => [
                'status' => WhatsAppWebSession::STATUS_DISCONNECTED,
                'last_disconnected_at' => now(),
                'last_error' => $payload['reason'] ?? null,
            ],
            'logged_out' => [
                'status' => WhatsAppWebSession::STATUS_LOGGED_OUT,
                'last_disconnected_at' => now(),
                'qr_raw' => null,
                'connected_phone' => null,
                'connected_name' => null,
            ],
            'send_failed' => [
                'last_error' => $payload['error'] ?? 'send failed',
            ],
            'send_ok' => null, // no DB update needed, just logged
            default => null,
        };

        if ($update !== null) {
            $session->update($update);
        }

        $this->info("[{$channel->id}] status:" . ($payload['event'] ?? 'unknown'));
    }

    private function storeInboundMessage(Channel $channel, array $payload): void
    {
        $from = $payload['from'] ?? null;
        $msgId = $payload['msgId'] ?? null;

        if (!$from || !$msgId) {
            return;
        }

        $exists = Message::withoutGlobalScopes()
            ->where('organization_id', $channel->organization_id)
            ->where('external_id', $msgId)
            ->exists();

        if ($exists) {
            return;
        }

        $conversation = $this->findOrCreateConversation($channel, $from, $payload['pushName'] ?? $from);

        $body = $payload['text'] ?? $this->mediaBody($payload['media'] ?? null);
        $type = $this->mapType($payload['media'] ?? null);

        $message = Message::create([
            'organization_id' => $channel->organization_id,
            'conversation_id' => $conversation->id,
            'body' => $body,
            'type' => $type,
            'direction' => 'inbound',
            'external_id' => $msgId,
            'metadata' => [
                'wa_web' => true,
                'wa_message_id' => $msgId,
                'wa_timestamp' => $payload['timestamp'] ?? null,
                'media_kind' => $payload['media'] ?? null,
            ],
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'unread_count' => $conversation->unread_count + 1,
        ]);

        $this->safeDispatch(fn() => MessageReceivedEvent::dispatch($message), 'MessageReceivedEvent');
    }

    private function safeDispatch(callable $fn, string $label): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning("wa:listen: broadcast failed ({$label}) — ignoring", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function updateAck(Channel $channel, array $payload): void
    {
        $msgId = $payload['msgId'] ?? null;
        $status = $payload['status'] ?? null;
        if (!$msgId || $status === null) {
            return;
        }

        $message = Message::withoutGlobalScopes()
            ->where('organization_id', $channel->organization_id)
            ->where('external_id', $msgId)
            ->first();

        if (!$message) {
            return;
        }

        $message->update([
            'metadata' => array_merge($message->metadata ?? [], [
                'wa_ack_status' => $status,
                'wa_ack_at' => now()->toISOString(),
            ]),
        ]);
    }

    private function findOrCreateConversation(Channel $channel, string $waId, string $contactName): Conversation
    {
        $conversation = Conversation::withoutGlobalScopes()
            ->where('organization_id', $channel->organization_id)
            ->where('channel_id', $channel->id)
            ->where('contact_identifier', $waId)
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
            'contact_identifier' => $waId,
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => now(),
        ]);

        $this->safeDispatch(fn() => ConversationCreated::dispatch($conversation), 'ConversationCreated');

        return $conversation;
    }

    private function mediaBody(?string $kind): string
    {
        return match ($kind) {
            'image' => '[Image]',
            'video' => '[Video]',
            'audio' => '[Audio]',
            'document' => '[Document]',
            'sticker' => '[Sticker]',
            'location' => '[Location]',
            'contact' => '[Contact]',
            default => '',
        };
    }

    private function mapType(?string $kind): string
    {
        return match ($kind) {
            'image', 'sticker' => 'image',
            'audio' => 'audio',
            'video' => 'video',
            'document' => 'file',
            default => 'text',
        };
    }
}
