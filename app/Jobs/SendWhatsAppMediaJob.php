<?php

namespace App\Jobs;

use App\Events\MessageSentEvent;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendWhatsAppMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];
    public int $timeout = 120;

    public function __construct(
        private readonly int $messageId,
        private readonly int $attachmentId,
    ) {
        $this->queue = 'critical';
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $message = Message::withoutGlobalScopes()->with(['conversation.channel'])->find($this->messageId);
        $attachment = MessageAttachment::withoutGlobalScopes()->find($this->attachmentId);

        if (!$message || !$attachment) {
            return;
        }

        $conversation = $message->conversation;
        $channel = $conversation->channel;

        if (!$channel || !$channel->isWhatsApp()) {
            Log::error('SendWhatsAppMediaJob: invalid channel', ['message_id' => $message->id]);
            return;
        }

        // Download file from storage to temp
        $disk = MessageAttachment::mediaDisk();
        $tempPath = tempnam(sys_get_temp_dir(), 'chatme_');
        file_put_contents($tempPath, Storage::disk($disk)->get($attachment->file_path));

        try {
            // Step 1: Upload to WhatsApp CDN
            $waMediaId = $whatsAppService->uploadMedia($channel, $tempPath, $attachment->mime_type);

            if (!$waMediaId) {
                $attachment->update(['status' => 'failed']);
                return;
            }

            // Step 2: Send media message
            $waMediaType = match ($attachment->media_type) {
                'image' => 'image',
                'video' => 'video',
                'audio' => 'audio',
                'document' => 'document',
                default => 'document',
            };

            $caption = $attachment->media_type === 'document' ? $attachment->file_name : ($message->body ?: null);

            $response = $whatsAppService->sendMediaMessage(
                $channel,
                $conversation->contact_identifier,
                $waMediaType,
                $waMediaId,
                $caption,
            );

            if ($response) {
                $waMessageId = $response['messages'][0]['id'] ?? null;

                $message->update([
                    'external_id' => $waMessageId,
                    'metadata' => array_merge($message->metadata ?? [], [
                        'wa_message_id' => $waMessageId,
                        'wa_media_id' => $waMediaId,
                        'wa_sent_at' => now()->toISOString(),
                    ]),
                ]);

                MessageSentEvent::dispatch($message);
            }
        } finally {
            @unlink($tempPath);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendWhatsAppMediaJob failed permanently', [
            'message_id' => $this->messageId,
            'attachment_id' => $this->attachmentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
