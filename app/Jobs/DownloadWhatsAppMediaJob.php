<?php

namespace App\Jobs;

use App\Models\Channel;
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

class DownloadWhatsAppMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];
    public int $timeout = 120;

    public function __construct(
        private readonly int $messageId,
        private readonly int $attachmentId,
        private readonly int $channelId,
    ) {
        $this->queue = 'media';
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $attachment = MessageAttachment::withoutGlobalScopes()->find($this->attachmentId);
        $message = Message::withoutGlobalScopes()->find($this->messageId);
        $channel = Channel::withoutGlobalScopes()->find($this->channelId);

        if (!$attachment || !$message || !$channel) {
            Log::warning('DownloadWhatsAppMediaJob: missing records', [
                'message_id' => $this->messageId,
                'attachment_id' => $this->attachmentId,
                'channel_id' => $this->channelId,
            ]);
            return;
        }

        $accessToken = $channel->getWhatsAppConfig('access_token');
        $mediaId = $attachment->external_media_id;

        if (!$accessToken || !$mediaId) {
            $attachment->update(['status' => 'failed']);
            Log::error('DownloadWhatsAppMediaJob: missing access_token or media_id', [
                'attachment_id' => $attachment->id,
            ]);
            return;
        }

        $attachment->update(['status' => 'processing']);

        // Step 1: Get the media URL from WhatsApp
        $mediaUrl = $whatsAppService->getMediaUrl($accessToken, $mediaId);

        if (!$mediaUrl) {
            $attachment->update(['status' => 'failed']);
            return;
        }

        // Step 2: Download the binary content
        $content = $whatsAppService->downloadMedia($accessToken, $mediaUrl);

        if (!$content) {
            $attachment->update(['status' => 'failed']);
            return;
        }

        // Step 3: Determine the channel type and build storage path
        $conversation = $message->conversation;
        $channelType = $conversation?->channel?->type ?? 'whatsapp';
        $extension = $this->guessExtension($attachment->mime_type, $attachment->file_name);

        $storagePath = MessageAttachment::storagePath(
            $message->organization_id,
            $channelType,
            $message->id,
            $extension
        );

        // Step 4: Store in S3 (or local disk)
        $disk = MessageAttachment::mediaDisk();

        Storage::disk($disk)->put($storagePath, $content, 'private');

        // Step 5: Update attachment record
        $fileSize = strlen($content);

        $attachment->update([
            'file_path' => $storagePath,
            'file_size' => $fileSize,
            'status' => 'ready',
        ]);

        Log::info('WhatsApp media downloaded', [
            'attachment_id' => $attachment->id,
            'message_id' => $message->id,
            'media_type' => $attachment->media_type,
            'file_size' => $fileSize,
            'path' => $storagePath,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DownloadWhatsAppMediaJob failed permanently', [
            'message_id' => $this->messageId,
            'attachment_id' => $this->attachmentId,
            'error' => $exception->getMessage(),
        ]);

        $attachment = MessageAttachment::withoutGlobalScopes()->find($this->attachmentId);
        $attachment?->update(['status' => 'failed']);
    }

    private function guessExtension(string $mimeType, string $fileName): string
    {
        // Try from filename first
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        if ($ext) {
            return strtolower($ext);
        }

        // Fallback from mime type
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/aac' => 'aac',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/opus' => 'opus',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/msword' => 'doc',
            'application/vnd.ms-excel' => 'xls',
            default => 'bin',
        };
    }
}
