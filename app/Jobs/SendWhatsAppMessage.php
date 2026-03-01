<?php

namespace App\Jobs;

use App\Events\MessageSentEvent;
use App\Models\Channel;
use App\Models\Message;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly Message $message,
    ) {
        $this->onQueue('critical');
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $conversation = $this->message->conversation;
        $channel = $conversation->channel;

        if (!$channel || !$channel->isWhatsApp()) {
            Log::error('SendWhatsAppMessage: invalid channel', [
                'message_id' => $this->message->id,
            ]);
            return;
        }

        $response = $whatsAppService->sendTextMessage(
            $channel,
            $conversation->contact_identifier,
            $this->message->body,
        );

        if ($response) {
            $waMessageId = $response['messages'][0]['id'] ?? null;

            $this->message->update([
                'external_id' => $waMessageId,
                'metadata' => array_merge($this->message->metadata ?? [], [
                    'wa_message_id' => $waMessageId,
                    'wa_sent_at' => now()->toISOString(),
                ]),
            ]);

            MessageSentEvent::dispatch($this->message);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendWhatsAppMessage failed permanently', [
            'message_id' => $this->message->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
