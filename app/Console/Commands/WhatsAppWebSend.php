<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\WhatsAppWebService;
use Illuminate\Console\Command;

class WhatsAppWebSend extends Command
{
    protected $signature = 'wa:send {channel : Channel ID} {to : Phone (ej 5215551234567)} {text* : Message text}';
    protected $description = 'Send a test text via a connected WhatsApp Web channel';

    public function handle(WhatsAppWebService $service): int
    {
        $channel = Channel::withoutGlobalScopes()->find((int) $this->argument('channel'));
        if (!$channel) {
            $this->error('Channel not found');
            return self::FAILURE;
        }

        $ref = $service->sendText(
            $channel,
            $this->argument('to'),
            implode(' ', $this->argument('text')),
        );

        $this->info("Sent (ref: {$ref})");
        return self::SUCCESS;
    }
}
