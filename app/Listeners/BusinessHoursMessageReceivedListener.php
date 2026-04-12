<?php

namespace App\Listeners;

use App\Events\MessageReceivedEvent;
use App\Services\BusinessHoursService;
use Illuminate\Contracts\Queue\ShouldQueue;

class BusinessHoursMessageReceivedListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private BusinessHoursService $service)
    {
    }

    public function handle(MessageReceivedEvent $event): void
    {
        $message = $event->message;

        if ($message->direction !== 'inbound') return;

        $conversation = $message->conversation;
        if (!$conversation) return;

        if ($this->service->shouldSendAutoResponse($conversation, 'out_of_hours')) {
            $this->service->sendAutoResponse($conversation, 'out_of_hours');
        }
    }
}
