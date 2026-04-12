<?php

namespace App\Services;

use App\Events\MessageSentEvent;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BusinessHoursService
{
    private const DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    public function isWithinBusinessHours(?Organization $org = null): bool
    {
        $org = $org ?? app('tenant');
        $settings = $org->settings ?? [];
        $bh = $settings['business_hours'] ?? [];

        if (empty($bh['enabled'])) {
            return true; // No business hours configured = always open
        }

        $timezone = $settings['timezone'] ?? 'America/Mexico_City';
        $now = Carbon::now($timezone);
        $dayName = self::DAYS[$now->dayOfWeek]; // 0=sunday, 1=monday...

        $daySchedule = $bh['schedule'][$dayName] ?? [];

        if (empty($daySchedule['enabled'])) {
            return false; // Day is disabled
        }

        $start = $daySchedule['start'] ?? null;
        $end = $daySchedule['end'] ?? null;

        if (!$start || !$end) {
            return false;
        }

        $startTime = Carbon::parse($start, $timezone);
        $endTime = Carbon::parse($end, $timezone);

        return $now->between($startTime, $endTime);
    }

    public function shouldSendAutoResponse(Conversation $conversation, string $type): bool
    {
        $org = Organization::withoutGlobalScopes()->find($conversation->organization_id);
        if (!$org) return false;

        $settings = $org->settings ?? [];
        $autoResponses = $settings['auto_responses'] ?? [];
        $config = $autoResponses[$type] ?? [];

        if (empty($config['enabled']) || empty($config['message'])) {
            return false;
        }

        // Check if already sent for this conversation
        $sent = $conversation->auto_responses_sent ?? [];
        if (!empty($sent[$type])) {
            return false;
        }

        // For out_of_hours, check if we're actually outside business hours
        if ($type === 'out_of_hours' && $this->isWithinBusinessHours($org)) {
            return false;
        }

        return true;
    }

    public function sendAutoResponse(Conversation $conversation, string $type): void
    {
        $org = Organization::withoutGlobalScopes()->find($conversation->organization_id);
        if (!$org) return;

        $settings = $org->settings ?? [];
        $config = ($settings['auto_responses'] ?? [])[$type] ?? [];
        $messageText = $config['message'] ?? '';

        if (!$messageText) return;

        $message = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'body' => $messageText,
            'type' => 'text',
            'direction' => 'outbound',
            'metadata' => [
                'auto_response' => true,
                'auto_response_type' => $type,
            ],
        ]);

        // Send via WhatsApp if applicable
        $channel = Channel::withoutGlobalScopes()->find($conversation->channel_id);
        if ($channel && $channel->isWhatsApp() && $conversation->contact_identifier) {
            SendWhatsAppMessage::dispatch($message);
        }

        $conversation->update(['last_message_at' => now()]);

        MessageSentEvent::dispatch($message);

        $this->markAutoResponseSent($conversation, $type);

        Log::info('Auto-response sent', [
            'conversation_id' => $conversation->id,
            'type' => $type,
        ]);
    }

    public function markAutoResponseSent(Conversation $conversation, string $type): void
    {
        $sent = $conversation->auto_responses_sent ?? [];
        $sent[$type] = now()->toISOString();
        $conversation->update(['auto_responses_sent' => $sent]);
    }

    public function checkAgentTimeouts(): void
    {
        $orgs = Organization::withoutGlobalScopes()
            ->where('status', 'active')
            ->get();

        foreach ($orgs as $org) {
            $settings = $org->settings ?? [];
            $config = ($settings['auto_responses'] ?? [])['agent_timeout'] ?? [];

            if (empty($config['enabled']) || empty($config['message']) || empty($config['timeout_minutes'])) {
                continue;
            }

            $cutoff = Carbon::now()->subMinutes((int) $config['timeout_minutes']);

            $conversations = Conversation::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->where('status', 'open')
                ->where('last_message_at', '<', $cutoff)
                ->where(function ($q) {
                    $q->whereNull('auto_responses_sent')
                      ->orWhereRaw("(auto_responses_sent->>'agent_timeout') IS NULL");
                })
                ->get();

            foreach ($conversations as $conversation) {
                // Verify last message is inbound (customer is waiting)
                $lastMsg = $conversation->messages()->latest('id')->first();
                if (!$lastMsg || $lastMsg->direction !== 'inbound') {
                    continue;
                }

                // Skip auto-response messages
                if (($lastMsg->metadata['auto_response'] ?? false)) {
                    continue;
                }

                $this->sendAutoResponse($conversation, 'agent_timeout');
            }
        }
    }
}
