<?php

namespace App\Listeners;

use App\Events\ConversationCreated;
use App\Events\MessageReceivedEvent;
use App\Services\BusinessHoursService;
use Illuminate\Contracts\Queue\ShouldQueue;

class BusinessHoursAutoResponseListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private BusinessHoursService $service)
    {
    }

    public function handleConversationCreated(ConversationCreated $event): void
    {
        $conversation = $event->conversation;

        if ($this->service->shouldSendAutoResponse($conversation, 'welcome')) {
            $this->service->sendAutoResponse($conversation, 'welcome');
        }

        if ($this->service->shouldSendAutoResponse($conversation, 'out_of_hours')) {
            $this->service->sendAutoResponse($conversation, 'out_of_hours');
        }
    }

    public function handleMessageReceived($event): void
    {
        $message = is_object($event) && property_exists($event, 'message') ? $event->message : null;
        if (!$message) return;

        if ($message->direction !== 'inbound') return;

        $conversation = $message->conversation;
        if (!$conversation) return;

        if ($this->service->shouldSendAutoResponse($conversation, 'out_of_hours')) {
            $this->service->sendAutoResponse($conversation, 'out_of_hours');
        }
    }
}
