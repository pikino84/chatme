<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceivedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, TenantBroadcastEvent;

    public readonly int $organizationId;

    public function __construct(public readonly Message $message)
    {
        $this->organizationId = $message->organization_id;
    }

    public function broadcastOn(): array
    {
        return [
            $this->conversationChannel($this->message->conversation_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->message->load('attachments');

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'body' => $this->message->body,
            'type' => $this->message->type,
            'direction' => $this->message->direction,
            'user_name' => $this->message->user?->name,
            'time' => $this->message->created_at->format('H:i'),
            'created_at' => $this->message->created_at->toISOString(),
            'attachments' => $this->message->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'media_type' => $a->media_type,
                'mime_type' => $a->mime_type,
                'file_size' => $a->sizeForHumans(),
                'duration' => $a->durationForHumans(),
                'status' => $a->status,
                'url' => $a->url(),
                'thumbnail_url' => $a->thumbnailUrl(),
            ])->toArray(),
        ];
    }
}
