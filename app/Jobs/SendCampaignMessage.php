<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\CampaignService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private Campaign $campaign,
        private CampaignRecipient $recipient,
    ) {
        $this->onQueue('default');
    }

    public function handle(CampaignService $campaignService): void
    {
        if ($this->campaign->isCanceled()) {
            return;
        }

        $contact = $this->recipient->contact;
        if (! $contact || ! $contact->phone) {
            $campaignService->markRecipientFailed($this->recipient, 'Contact has no phone number.');
            return;
        }

        try {
            $channel = $this->campaign->channel;

            if ($channel->type === 'whatsapp') {
                $whatsapp = app(WhatsAppService::class);
                $whatsapp->sendTextMessage($channel, $contact->phone, $this->campaign->message_template);
            }

            $campaignService->markRecipientSent($this->recipient);
        } catch (\Throwable $e) {
            Log::error('Campaign message failed', [
                'campaign_id' => $this->campaign->id,
                'recipient_id' => $this->recipient->id,
                'error' => $e->getMessage(),
            ]);

            $campaignService->markRecipientFailed($this->recipient, $e->getMessage());
        }
    }
}
