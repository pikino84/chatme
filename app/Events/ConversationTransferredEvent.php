<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationTransferredEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, TenantBroadcastEvent;

    public readonly int $organizationId;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly int $fromUserId,
        public readonly int $toUserId,
    ) {
        $this->organizationId = $conversation->organization_id;
    }

    public function broadcastOn(): array
    {
        return [
            $this->organizationChannel(),
            $this->userChannel($this->fromUserId),
            $this->userChannel($this->toUserId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'from_user_id' => $this->fromUserId,
            'to_user_id' => $this->toUserId,
            'contact_name' => $this->conversation->contact_name,
        ];
    }
}
