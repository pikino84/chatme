<?php

namespace App\Jobs;

use App\Events\ConversationCreated;
use App\Events\MessageSentEvent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ForwardWhatsAppMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly int $channelId,
        private readonly array $messageIds,
        private readonly string $recipientPhone,
        private readonly int $userId,
        private readonly int $organizationId,
    ) {
        $this->onQueue('critical');
    }

    public function handle(): void
    {
        $channel = Channel::withoutGlobalScopes()->find($this->channelId);

        if (!$channel || !$channel->isWhatsApp()) {
            Log::error('ForwardWhatsAppMessages: invalid channel', ['channel_id' => $this->channelId]);
            return;
        }

        $conversation = $this->findOrCreateConversation($channel);

        $sourceMessages = Message::withoutGlobalScopes()
            ->with('attachments')
            ->whereIn('id', $this->messageIds)
            ->where('organization_id', $this->organizationId)
            ->oldest()
            ->get();

        foreach ($sourceMessages as $sourceMsg) {
            $this->forwardMessage($sourceMsg, $conversation, $channel);
        }

        $conversation->update(['last_message_at' => now()]);
    }

    private function forwardMessage(Message $sourceMsg, Conversation $conversation, Channel $channel): void
    {
        $metadata = [
            'forwarded' => true,
            'forwarded_from_message_id' => $sourceMsg->id,
            'forwarded_from_conversation_id' => $sourceMsg->conversation_id,
        ];

        $newMessage = Message::create([
            'organization_id' => $this->organizationId,
            'conversation_id' => $conversation->id,
            'user_id' => $this->userId,
            'body' => $sourceMsg->body,
            'type' => $sourceMsg->type,
            'direction' => 'outbound',
            'metadata' => $metadata,
        ]);

        if ($sourceMsg->attachments->isNotEmpty()) {
            foreach ($sourceMsg->attachments as $srcAttachment) {
                $newAttachment = MessageAttachment::create([
                    'organization_id' => $this->organizationId,
                    'message_id' => $newMessage->id,
                    'file_name' => $srcAttachment->file_name,
                    'file_path' => $srcAttachment->file_path,
                    'thumbnail_path' => $srcAttachment->thumbnail_path,
                    'file_size' => $srcAttachment->file_size,
                    'mime_type' => $srcAttachment->mime_type,
                    'media_type' => $srcAttachment->media_type,
                    'width' => $srcAttachment->width,
                    'height' => $srcAttachment->height,
                    'duration' => $srcAttachment->duration,
                    'status' => 'ready',
                ]);

                SendWhatsAppMediaJob::dispatch($newMessage->id, $newAttachment->id);
            }
        } elseif (!empty($newMessage->body)) {
            SendWhatsAppMessage::dispatch($newMessage);
        }
    }

    private function findOrCreateConversation(Channel $channel): Conversation
    {
        return DB::transaction(function () use ($channel) {
            $conversation = Conversation::withoutGlobalScopes()
                ->where('organization_id', $this->organizationId)
                ->where('channel_id', $channel->id)
                ->where('contact_identifier', $this->recipientPhone)
                ->where('status', '!=', 'closed')
                ->lockForUpdate()
                ->first();

            if ($conversation) {
                return $conversation;
            }

            $contactName = $this->recipientPhone;
            $contact = Contact::withoutGlobalScopes()
                ->where('organization_id', $this->organizationId)
                ->where('phone', $this->recipientPhone)
                ->first();

            if ($contact) {
                $contactName = $contact->name;
            }

            $conversation = Conversation::create([
                'organization_id' => $this->organizationId,
                'brand_id' => $channel->brand_id,
                'channel_id' => $channel->id,
                'contact_name' => $contactName,
                'contact_identifier' => $this->recipientPhone,
                'contact_id' => $contact?->id,
                'status' => 'open',
                'priority' => 'normal',
                'last_message_at' => now(),
            ]);

            ConversationCreated::dispatch($conversation);

            return $conversation;
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ForwardWhatsAppMessages failed permanently', [
            'channel_id' => $this->channelId,
            'recipient' => $this->recipientPhone,
            'message_ids' => $this->messageIds,
            'error' => $exception->getMessage(),
        ]);
    }
}
