<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MessageAttachment extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'message_id',
        'file_name',
        'file_path',
        'thumbnail_path',
        'file_size',
        'mime_type',
        'media_type',
        'width',
        'height',
        'duration',
        'external_media_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public static function mediaDisk(): string
    {
        return config('filesystems.disks.chatme-media.driver') === 'local'
            ? 'local'
            : 'chatme-media';
    }

    public static function storagePath(int $orgId, string $channelType, int $messageId, string $extension): string
    {
        return sprintf(
            'chatme/%d/%s/%s/%s/%d.%s',
            $orgId,
            $channelType,
            now()->format('Y'),
            now()->format('m'),
            $messageId,
            $extension
        );
    }

    public function url(): ?string
    {
        if ($this->status !== 'ready') {
            return null;
        }

        $disk = Storage::disk(static::mediaDisk());

        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($this->file_path, now()->addMinutes(15));
            } catch (\RuntimeException $e) {
                // Local disk doesn't support temporary URLs
            }
        }

        return $disk->url($this->file_path);
    }

    public function thumbnailUrl(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }

        $disk = Storage::disk(static::mediaDisk());

        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($this->thumbnail_path, now()->addMinutes(15));
            } catch (\RuntimeException $e) {
                // Local disk doesn't support temporary URLs
            }
        }

        return $disk->url($this->thumbnail_path);
    }

    public function sizeForHumans(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    public function durationForHumans(): ?string
    {
        if (!$this->duration) {
            return null;
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function isImage(): bool
    {
        return $this->media_type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    public function isAudio(): bool
    {
        return $this->media_type === 'audio';
    }

    public function isDocument(): bool
    {
        return $this->media_type === 'document';
    }

    public function isSticker(): bool
    {
        return $this->media_type === 'sticker';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
