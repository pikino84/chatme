<?php

namespace App\Listeners;

use App\Events\ConversationCreated;
use App\Services\BusinessHoursService;
use Illuminate\Contracts\Queue\ShouldQueue;

class BusinessHoursConversationCreatedListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private BusinessHoursService $service)
    {
    }

    public function handle(ConversationCreated $event): void
    {
        $conversation = $event->conversation;

        // Out of hours takes priority over welcome (mutually exclusive)
        if ($this->service->shouldSendAutoResponse($conversation, 'out_of_hours')) {
            $this->service->sendAutoResponse($conversation, 'out_of_hours');
        } elseif ($this->service->shouldSendAutoResponse($conversation, 'welcome')) {
            $this->service->sendAutoResponse($conversation, 'welcome');
        }
    }
}
