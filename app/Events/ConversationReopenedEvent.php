<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationReopenedEvent implements ShouldBroadcast
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
            'conversation_id' => $this->conversation->id,
            'contact_name' => $this->conversation->contact_name,
            'status' => $this->conversation->status,
        ];
    }
}
