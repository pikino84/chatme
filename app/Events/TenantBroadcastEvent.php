<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

trait TenantBroadcastEvent
{
    protected function organizationChannel(): PrivateChannel
    {
        return new PrivateChannel("organization.{$this->organizationId}");
    }

    protected function conversationChannel(int $conversationId): PrivateChannel
    {
        return new PrivateChannel("conversation.{$this->organizationId}.{$conversationId}");
    }

    protected function userChannel(int $userId): PrivateChannel
    {
        return new PrivateChannel("user.{$this->organizationId}.{$userId}");
    }
}
