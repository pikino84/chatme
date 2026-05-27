<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Services\WhatsAppWebService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Descarga el binario de un mensaje multimedia de WhatsApp Directo (Evolution API)
 * y lo sube al disco chatme-media (S3 en producción). Phase 22.4.
 */
class DownloadEvolutionMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];
    public int $timeout = 120;

    /**
     * @param array $messageKey  key del mensaje de Evolution {remoteJid, fromMe, id}
     */
    public function __construct(
        private readonly int $messageId,
        private readonly int $attachmentId,
        private readonly int $channelId,
        private readonly array $messageKey,
    ) {
        $this->queue = 'media';
    }

    public function handle(WhatsAppWebService $service): void
    {
        $attachment = MessageAttachment::withoutGlobalScopes()->find($this->attachmentId);
        $message = Message::withoutGlobalScopes()->find($this->messageId);
        $channel = Channel::withoutGlobalScopes()->find($this->channelId);

        if (! $attachment || ! $message || ! $channel) {
            Log::warning('DownloadEvolutionMediaJob: registros faltantes', [
                'message_id' => $this->messageId,
                'attachment_id' => $this->attachmentId,
                'channel_id' => $this->channelId,
            ]);
            return;
        }

        $attachment->update(['status' => 'processing']);

        $base64 = $service->fetchMediaBase64($channel, $this->messageKey);
        if (! $base64) {
            $attachment->update(['status' => 'failed']);
            return;
        }

        $content = base64_decode($base64, true);
        if ($content === false || $content === '') {
            $attachment->update(['status' => 'failed']);
            return;
        }

        $extension = $this->guessExtension($attachment->mime_type ?? '', $attachment->file_name ?? '');
        $storagePath = MessageAttachment::storagePath(
            $message->organization_id,
            'whatsapp_web',
            $message->id,
            $extension,
        );

        Storage::disk(MessageAttachment::mediaDisk())->put($storagePath, $content, 'private');

        $attachment->update([
            'file_path' => $storagePath,
            'file_size' => strlen($content),
            'status' => 'ready',
        ]);

        Log::info('WA Directo: multimedia descargada', [
            'attachment_id' => $attachment->id,
            'message_id' => $message->id,
            'media_type' => $attachment->media_type,
            'path' => $storagePath,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DownloadEvolutionMediaJob falló permanentemente', [
            'message_id' => $this->messageId,
            'attachment_id' => $this->attachmentId,
            'error' => $exception->getMessage(),
        ]);

        MessageAttachment::withoutGlobalScopes()
            ->find($this->attachmentId)
            ?->update(['status' => 'failed']);
    }

    private function guessExtension(string $mimeType, string $fileName): string
    {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        if ($ext) {
            return strtolower($ext);
        }

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
            'audio/opus', 'audio/ogg; codecs=opus' => 'opus',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/msword' => 'doc',
            'application/vnd.ms-excel' => 'xls',
            default => 'bin',
        };
    }
}
