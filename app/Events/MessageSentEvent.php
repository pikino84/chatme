<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSentEvent implements ShouldBroadcast
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
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'user_id' => $this->message->user_id,
            'body' => $this->message->body,
            'type' => $this->message->type,
            'direction' => $this->message->direction,
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }
}
