<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationClosedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, TenantBroadcastEvent;

    public readonly int $organizationId;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly ?int $closedByUserId = null,
    ) {
        $this->organizationId = $conversation->organization_id;
    }

    public function broadcastOn(): array
    {
        $channels = [$this->organizationChannel()];

        if ($this->conversation->assigned_user_id) {
            $channels[] = $this->userChannel($this->conversation->assigned_user_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'closed_by' => $this->closedByUserId,
            'closed_at' => $this->conversation->closed_at?->toISOString(),
        ];
    }
}
