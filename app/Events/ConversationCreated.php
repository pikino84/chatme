<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, TenantBroadcastEvent;

    public readonly int $organizationId;

    public function __construct(public readonly Conversation $conversation)
    {
        $this->organizationId = $conversation->organization_id;
    }

    public function broadcastOn(): array
    {
        return [$this->organizationChannel()];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->conversation->id,
            'channel_id' => $this->conversation->channel_id,
            'contact_name' => $this->conversation->contact_name,
            'contact_identifier' => $this->conversation->contact_identifier,
            'status' => $this->conversation->status,
            'priority' => $this->conversation->priority,
            'created_at' => $this->conversation->created_at->toISOString(),
        ];
    }
}
