<?php

namespace App\Http\Controllers\Tenant;

use App\Events\MessageSentEvent;
use App\Http\Controllers\Controller;
use App\Jobs\ForwardWhatsAppMessages;
use App\Jobs\SendWhatsAppMediaJob;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MessageController extends Controller
{
    use AuthorizesRequests;
    public function poll(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $afterId = (int) $request->query('after_id', 0);

        $messages = $conversation->messages()
            ->with(['user', 'attachments'])
            ->when($afterId, fn ($q) => $q->where('id', '>', $afterId))
            ->oldest()
            ->limit(50)
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'body' => e($msg->body),
                'type' => $msg->type,
                'direction' => $msg->direction,
                'is_forwarded' => (bool) (($msg->metadata ?? [])['forwarded'] ?? false),
                'user_name' => $msg->user?->name ?? ($msg->isInbound() ? null : 'Agent'),
                'time' => $msg->created_at->format('H:i'),
                'attachments' => $msg->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'file_name' => $a->file_name,
                    'media_type' => $a->media_type,
                    'mime_type' => $a->mime_type,
                    'file_size' => $a->sizeForHumans(),
                    'duration' => $a->durationForHumans(),
                    'status' => $a->status,
                    'url' => $a->url(),
                    'thumbnail_url' => $a->thumbnailUrl(),
                ]),
            ]);

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, Conversation $conversation)
    {
        $type = $request->input('type', 'text');
        $hasFile = $request->hasFile('file');

        if ($type === 'internal_note') {
            $this->authorize('sendInternalNote', [Message::class, $conversation]);
        } else {
            $this->authorize('send', [Message::class, $conversation]);
        }

        $rules = [
            'type' => 'nullable|in:text,internal_note,image,video,audio,file',
        ];

        if ($hasFile) {
            $rules['file'] = 'required|file|max:16384'; // 16MB max
            $rules['body'] = 'nullable|string|max:5000';
        } else {
            $rules['body'] = 'required|string|max:5000';
        }

        $request->validate($rules);

        $body = strip_tags($request->input('body', ''));

        // Determine message type from file if present
        if ($hasFile) {
            $file = $request->file('file');
            $mime = $file->getMimeType();
            $type = $this->detectMediaType($mime);
        }

        $message = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'body' => $body ?: ($hasFile ? null : $body),
            'type' => $type,
            'direction' => 'outbound',
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Handle file attachment
        if ($hasFile) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension() ?: 'bin';
            $channelType = $conversation->channel?->type ?? 'unknown';

            $storagePath = MessageAttachment::storagePath(
                $conversation->organization_id,
                $channelType,
                $message->id,
                $extension,
            );

            $disk = MessageAttachment::mediaDisk();
            Storage::disk($disk)->put($storagePath, file_get_contents($file), 'private');

            $attachment = MessageAttachment::create([
                'organization_id' => $conversation->organization_id,
                'message_id' => $message->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $storagePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'media_type' => str_replace(['video', 'file'], ['video', 'document'], $type === 'file' ? 'document' : $type),
                'status' => 'ready',
            ]);

            // Send media via WhatsApp
            if ($type !== 'internal_note' && $conversation->channel?->isWhatsApp()) {
                SendWhatsAppMediaJob::dispatch($message->id, $attachment->id);
            }
        } else {
            // Broadcast and send text via WhatsApp
            MessageSentEvent::dispatch($message);

            if ($type !== 'internal_note' && $conversation->channel?->isWhatsApp()) {
                SendWhatsAppMessage::dispatch($message);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => $message->load('attachments')], 201);
        }

        return back();
    }

    public function forward(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $request->validate([
            'message_ids' => 'required|array|min:1|max:25',
            'message_ids.*' => 'integer',
            'recipients' => 'required|array|min:1|max:10',
            'recipients.*' => 'string|regex:/^\+?\d{10,15}$/',
        ]);

        $channel = $conversation->channel;

        if (!$channel || !$channel->isWhatsApp()) {
            return response()->json(['error' => 'Solo se pueden reenviar mensajes de conversaciones WhatsApp.'], 422);
        }

        $messages = $conversation->messages()
            ->with('attachments')
            ->whereIn('id', $request->input('message_ids'))
            ->where('type', '!=', 'internal_note')
            ->oldest()
            ->get();

        if ($messages->isEmpty()) {
            return response()->json(['error' => 'No se encontraron mensajes válidos.'], 422);
        }

        $messageIds = $messages->pluck('id')->toArray();

        // Check 24h window for all recipients in one query
        $sent = [];
        $skipped = [];
        $cutoff = Carbon::now()->subHours(24);
        $recipients = $request->input('recipients');

        $phonesWithWindow = Message::withoutGlobalScopes()
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.channel_id', $channel->id)
            ->whereIn('conversations.contact_identifier', $recipients)
            ->where('messages.organization_id', $conversation->organization_id)
            ->where('messages.direction', 'inbound')
            ->where('messages.created_at', '>=', $cutoff)
            ->distinct()
            ->pluck('conversations.contact_identifier')
            ->toArray();

        foreach ($recipients as $phone) {
            if (in_array($phone, $phonesWithWindow)) {
                ForwardWhatsAppMessages::dispatch(
                    $channel->id,
                    $messageIds,
                    $phone,
                    $request->user()->id,
                    $conversation->organization_id,
                );
                $sent[] = $phone;
            } else {
                $skipped[] = $phone;
            }
        }

        return response()->json([
            'success' => true,
            'forwarded_count' => count($messageIds) * count($sent),
            'sent' => $sent,
            'skipped' => $skipped,
        ]);
    }

    private function detectMediaType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) return 'image';
        if (str_starts_with($mimeType, 'video/')) return 'video';
        if (str_starts_with($mimeType, 'audio/')) return 'audio';
        return 'file';
    }
}
