<?php

namespace App\Listeners;

use App\Events\ConversationCreated;
use App\Services\AutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AutomationListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private AutomationService $automationService)
    {
    }

    public function handle(ConversationCreated $event): void
    {
        $this->automationService->handleNewConversation($event->conversation);
    }
}
