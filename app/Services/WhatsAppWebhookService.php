<?php

namespace App\Services;

use App\Events\ConversationCreated;
use App\Events\MessageReceivedEvent;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookService
{
    public function process(Channel $channel, array $payload): void
    {
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];

                if (!$this->validatePhoneNumberId($channel, $value)) {
                    Log::warning('WhatsApp webhook: phone_number_id mismatch', [
                        'channel_id' => $channel->id,
                        'expected' => $channel->getWhatsAppConfig('phone_number_id'),
                        'received' => $value['metadata']['phone_number_id'] ?? 'none',
                    ]);
                    continue;
                }

                $messages = $value['messages'] ?? [];
                $contacts = $value['contacts'] ?? [];

                foreach ($messages as $messageData) {
                    $this->processMessage($channel, $messageData, $contacts);
                }
            }
        }
    }

    private function validatePhoneNumberId(Channel $channel, array $value): bool
    {
        $expectedId = $channel->getWhatsAppConfig('phone_number_id');
        $receivedId = $value['metadata']['phone_number_id'] ?? null;

        return $expectedId && $receivedId && $expectedId === $receivedId;
    }

    private function processMessage(Channel $channel, array $messageData, array $contacts): void
    {
        $waId = $messageData['from'] ?? null;
        $externalId = $messageData['id'] ?? null;

        if (!$waId || !$externalId) {
            return;
        }

        // Prevent duplicate processing
        $existingMessage = Message::withoutGlobalScopes()
            ->where('organization_id', $channel->organization_id)
            ->where('external_id', $externalId)
            ->exists();

        if ($existingMessage) {
            return;
        }

        $contactName = $this->resolveContactName($waId, $contacts);
        $conversation = $this->findOrCreateConversation($channel, $waId, $contactName);

        $message = Message::create([
            'organization_id' => $channel->organization_id,
            'conversation_id' => $conversation->id,
            'body' => $this->extractBody($messageData),
            'type' => $this->mapMessageType($messageData['type'] ?? 'text'),
            'direction' => 'inbound',
            'external_id' => $externalId,
            'metadata' => [
                'wa_message_id' => $externalId,
                'wa_timestamp' => $messageData['timestamp'] ?? null,
                'wa_type' => $messageData['type'] ?? 'text',
            ],
        ]);

        $conversation->update(['last_message_at' => now()]);

        MessageReceivedEvent::dispatch($message);
    }

    private function findOrCreateConversation(Channel $channel, string $waId, string $contactName): Conversation
    {
        $conversation = Conversation::withoutGlobalScopes()
            ->where('organization_id', $channel->organization_id)
            ->where('channel_id', $channel->id)
            ->where('contact_identifier', $waId)
            ->where('status', '!=', 'closed')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $conversation = Conversation::create([
            'organization_id' => $channel->organization_id,
            'channel_id' => $channel->id,
            'contact_name' => $contactName,
            'contact_identifier' => $waId,
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => now(),
        ]);

        ConversationCreated::dispatch($conversation);

        return $conversation;
    }

    private function resolveContactName(string $waId, array $contacts): string
    {
        foreach ($contacts as $contact) {
            if (($contact['wa_id'] ?? '') === $waId) {
                return $contact['profile']['name'] ?? $waId;
            }
        }

        return $waId;
    }

    private function extractBody(array $messageData): ?string
    {
        $type = $messageData['type'] ?? 'text';

        return match ($type) {
            'text' => $messageData['text']['body'] ?? null,
            'image' => $messageData['image']['caption'] ?? '[Image]',
            'audio' => '[Audio]',
            'video' => $messageData['video']['caption'] ?? '[Video]',
            'document' => $messageData['document']['filename'] ?? '[Document]',
            'location' => sprintf(
                '[Location: %s, %s]',
                $messageData['location']['latitude'] ?? '0',
                $messageData['location']['longitude'] ?? '0'
            ),
            default => "[{$type}]",
        };
    }

    private function mapMessageType(string $waType): string
    {
        return match ($waType) {
            'text' => 'text',
            'image' => 'image',
            'audio' => 'audio',
            'video', 'document' => 'file',
            default => 'text',
        };
    }
}
