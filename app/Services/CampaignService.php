<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use Carbon\Carbon;

class CampaignService
{
    public function __construct(private BillingService $billingService)
    {
    }

    public function create(int $organizationId, int $userId, array $data): Campaign
    {
        $org = \App\Models\Organization::findOrFail($organizationId);

        if (! $this->billingService->checkFeature($org, 'campaigns_enabled')) {
            throw new \App\Exceptions\BillingLimitException('Campaigns are not available on your current plan.');
        }

        return Campaign::create([
            'organization_id' => $organizationId,
            'channel_id' => $data['channel_id'],
            'created_by' => $userId,
            'name' => $data['name'],
            'type' => 'broadcast',
            'status' => 'draft',
            'message_template' => $data['message_template'],
        ]);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        if (! $campaign->canBeEdited()) {
            throw new \LogicException('Campaign cannot be edited in its current status.');
        }

        $campaign->update(array_intersect_key($data, array_flip([
            'name', 'channel_id', 'message_template',
        ])));

        return $campaign->fresh();
    }

    public function addRecipients(Campaign $campaign, array $contactIds): int
    {
        if (! $campaign->canBeEdited()) {
            throw new \LogicException('Cannot add recipients to a non-draft campaign.');
        }

        $existingIds = $campaign->recipients()->pluck('contact_id')->toArray();
        $newIds = array_diff($contactIds, $existingIds);

        $records = [];
        foreach ($newIds as $contactId) {
            $records[] = [
                'campaign_id' => $campaign->id,
                'contact_id' => $contactId,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($records)) {
            CampaignRecipient::insert($records);
            $campaign->update(['total_recipients' => $campaign->recipients()->count()]);
        }

        return count($records);
    }

    public function removeRecipient(Campaign $campaign, int $contactId): void
    {
        if (! $campaign->canBeEdited()) {
            throw new \LogicException('Cannot remove recipients from a non-draft campaign.');
        }

        $campaign->recipients()->where('contact_id', $contactId)->delete();
        $campaign->update(['total_recipients' => $campaign->recipients()->count()]);
    }

    public function schedule(Campaign $campaign, Carbon $scheduledAt): Campaign
    {
        if (! $campaign->canBeSent()) {
            throw new \LogicException('Campaign cannot be scheduled without recipients.');
        }

        $campaign->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);

        return $campaign->fresh();
    }

    public function send(Campaign $campaign): Campaign
    {
        if (! $campaign->canBeSent() && $campaign->status !== 'scheduled') {
            throw new \LogicException('Campaign cannot be sent.');
        }

        $campaign->update(['status' => 'running']);

        // Dispatch job for each pending recipient
        $campaign->recipients()
            ->where('status', 'pending')
            ->each(function (CampaignRecipient $recipient) use ($campaign) {
                \App\Jobs\SendCampaignMessage::dispatch($campaign, $recipient);
            });

        return $campaign->fresh();
    }

    public function cancel(Campaign $campaign): Campaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled', 'running'])) {
            throw new \LogicException('Campaign cannot be canceled.');
        }

        $campaign->update(['status' => 'canceled']);

        return $campaign->fresh();
    }

    public function markRecipientSent(CampaignRecipient $recipient): void
    {
        $recipient->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $campaign = $recipient->campaign;
        $campaign->increment('sent_count');

        $this->checkCompletion($campaign);
    }

    public function markRecipientFailed(CampaignRecipient $recipient, string $error): void
    {
        $recipient->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);

        $campaign = $recipient->campaign;
        $campaign->increment('failed_count');

        $this->checkCompletion($campaign);
    }

    private function checkCompletion(Campaign $campaign): void
    {
        $campaign->refresh();
        $processed = $campaign->sent_count + $campaign->failed_count;

        if ($processed >= $campaign->total_recipients) {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    public function getStats(Campaign $campaign): array
    {
        return [
            'total' => $campaign->total_recipients,
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count,
            'pending' => $campaign->total_recipients - $campaign->sent_count - $campaign->failed_count,
            'delivery_rate' => $campaign->total_recipients > 0
                ? round(($campaign->sent_count / $campaign->total_recipients) * 100, 1)
                : 0,
        ];
    }
}
