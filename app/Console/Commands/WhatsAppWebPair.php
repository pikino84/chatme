<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\WhatsAppWebService;
use Illuminate\Console\Command;

class WhatsAppWebPair extends Command
{
    protected $signature = 'wa:pair {channel : Channel ID} {--reset : Force re-pairing by clearing auth}';
    protected $description = 'Request a WhatsApp Web pairing (QR) for a channel via the bridge';

    public function handle(WhatsAppWebService $service): int
    {
        $channelId = (int) $this->argument('channel');
        $channel = Channel::withoutGlobalScopes()->find($channelId);

        if (!$channel) {
            $this->error("Channel #{$channelId} not found");
            return self::FAILURE;
        }

        if ($channel->type !== WhatsAppWebService::CHANNEL_TYPE) {
            $this->error("Channel #{$channelId} is not whatsapp_web (type: {$channel->type})");
            return self::FAILURE;
        }

        $session = $service->requestPair($channel, $this->option('reset'));

        $this->info("Pair requested for channel #{$channel->id} ({$channel->name})");
        $this->line("Session status: {$session->status}");
        $this->line('QR will arrive via wa:listen and be stored in whatsapp_web_sessions.qr_raw');
        $this->line('Run `php artisan wa:qr ' . $channel->id . '` to print the QR once ready.');

        return self::SUCCESS;
    }
}
