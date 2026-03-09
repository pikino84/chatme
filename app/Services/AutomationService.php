<?php

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationAssignment;
use App\Models\Message;
use App\Models\SaasAlert;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AutomationService
{
    public function handleNewConversation(Conversation $conversation): void
    {
        $orgId = $conversation->organization_id;

        $rules = AutomationRule::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->where(function ($q) use ($conversation) {
                $q->whereNull('channel_id')
                  ->orWhere('channel_id', $conversation->channel_id);
            })
            ->orderBy('type')
            ->get();

        foreach ($rules as $rule) {
            match ($rule->type) {
                'auto_assign' => $this->executeAutoAssign($conversation, $rule),
                'auto_response' => $this->executeAutoResponse($conversation, $rule),
                default => null,
            };
        }
    }

    public function executeAutoAssign(Conversation $conversation, AutomationRule $rule): void
    {
        if ($conversation->assigned_user_id) {
            return;
        }

        $method = $rule->getConfigValue('method', 'round_robin');

        $agent = match ($method) {
            'least_busy' => $this->findLeastBusyAgent($conversation->organization_id),
            default => $this->findRoundRobinAgent($conversation->organization_id),
        };

        if (! $agent) {
            return;
        }

        $conversation->update(['assigned_user_id' => $agent->id]);

        ConversationAssignment::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => $agent->id,
            'assigned_by' => null, // system assignment
            'assigned_at' => now(),
        ]);

        Log::info('Auto-assigned conversation', [
            'conversation_id' => $conversation->id,
            'agent_id' => $agent->id,
            'method' => $method,
        ]);
    }

    public function executeAutoResponse(Conversation $conversation, AutomationRule $rule): void
    {
        $message = $rule->getConfigValue('message');

        if (! $message) {
            return;
        }

        Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'body' => $message,
            'type' => 'text',
            'direction' => 'outbound',
            'metadata' => ['auto_response' => true],
        ]);

        // Try to send via channel if WhatsApp
        $this->sendViaChannel($conversation, $message);
    }

    public function checkStaleConversations(): int
    {
        $rules = AutomationRule::withoutGlobalScopes()
            ->where('type', 'stale_alert')
            ->where('is_active', true)
            ->get();

        $alertCount = 0;

        foreach ($rules as $rule) {
            $thresholdMinutes = $rule->getConfigValue('threshold_minutes', 30);
            $cutoff = now()->subMinutes($thresholdMinutes);

            $staleConversations = Conversation::withoutGlobalScopes()
                ->where('organization_id', $rule->organization_id)
                ->where('status', 'open')
                ->where(function ($q) use ($cutoff) {
                    $q->where('last_message_at', '<', $cutoff)
                      ->orWhereNull('last_message_at');
                })
                ->when($rule->channel_id, fn ($q) => $q->where('channel_id', $rule->channel_id))
                ->whereNull('assigned_user_id')
                ->get();

            foreach ($staleConversations as $conversation) {
                // Avoid duplicate alerts for same conversation
                $existingAlert = SaasAlert::where('organization_id', $rule->organization_id)
                    ->where('type', 'warning')
                    ->where('title', 'Conversación sin atender')
                    ->where('message', 'like', "%#{$conversation->id}%")
                    ->where('is_active', true)
                    ->whereNull('resolved_at')
                    ->exists();

                if (! $existingAlert) {
                    SaasAlert::create([
                        'organization_id' => $rule->organization_id,
                        'type' => 'warning',
                        'title' => 'Conversación sin atender',
                        'message' => "La conversación #{$conversation->id} de {$conversation->contact_name} lleva más de {$thresholdMinutes} min sin respuesta.",
                        'is_active' => true,
                        'starts_at' => now(),
                    ]);
                    $alertCount++;
                }
            }
        }

        return $alertCount;
    }

    private function findRoundRobinAgent(int $organizationId): ?User
    {
        // Get the agent with the oldest last assignment (or never assigned)
        return User::withoutGlobalScopes()
            ->where('users.organization_id', $organizationId)
            ->where('users.is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['agent', 'supervisor']))
            ->leftJoin('conversation_assignments', function ($join) {
                $join->on('users.id', '=', 'conversation_assignments.user_id')
                     ->whereNull('conversation_assignments.unassigned_at');
            })
            ->select('users.*')
            ->selectRaw('MAX(conversation_assignments.assigned_at) as last_assigned')
            ->groupBy('users.id')
            ->orderByRaw('MAX(conversation_assignments.assigned_at) IS NULL DESC')
            ->orderBy('last_assigned', 'asc')
            ->first();
    }

    private function findLeastBusyAgent(int $organizationId): ?User
    {
        return User::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['agent', 'supervisor']))
            ->withCount(['assignedConversations' => fn ($q) => $q->withoutGlobalScopes()->where('status', 'open')])
            ->orderBy('assigned_conversations_count', 'asc')
            ->first();
    }

    private function sendViaChannel(Conversation $conversation, string $messageText): void
    {
        try {
            $channel = Channel::withoutGlobalScopes()->find($conversation->channel_id);

            if (! $channel || ! $conversation->contact_identifier) {
                return;
            }

            if ($channel->type === 'whatsapp') {
                app(WhatsAppService::class)->sendTextMessage(
                    $channel,
                    $conversation->contact_identifier,
                    $messageText
                );
            }
        } catch (\Throwable $e) {
            Log::error('Auto-response send failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
