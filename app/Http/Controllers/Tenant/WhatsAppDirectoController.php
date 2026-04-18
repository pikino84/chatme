<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppWebSession;
use App\Services\WhatsAppWebService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppDirectoController extends Controller
{
    public function __construct(private WhatsAppWebService $service)
    {
    }

    public function index(Request $request)
    {
        $channels = Channel::where('type', WhatsAppWebService::CHANNEL_TYPE)
            ->with('whatsappWebSession')
            ->orderByDesc('created_at')
            ->get();

        return view('whatsapp-directo.index', compact('channels'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $channel = new Channel();
        $channel->organization_id = $request->user()->organization_id;
        $channel->type = WhatsAppWebService::CHANNEL_TYPE;
        $channel->name = $data['name'];
        $channel->is_active = true;
        $channel->save();

        $this->service->requestPair($channel);

        return redirect()
            ->route('whatsapp-directo.pair', $channel)
            ->with('status', "Canal «{$channel->name}» creado. Escanea el QR para vincular.");
    }

    public function pair(Channel $channel)
    {
        $this->ensureWebChannel($channel);

        $session = $channel->whatsappWebSession;
        if (!$session || $session->status === WhatsAppWebSession::STATUS_DISCONNECTED) {
            $this->service->requestPair($channel);
        }

        return view('whatsapp-directo.pair', [
            'channel' => $channel->fresh('whatsappWebSession'),
        ]);
    }

    public function repair(Channel $channel, Request $request): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->service->requestPair($channel, resetAuth: (bool) $request->boolean('reset'));

        return response()->json(['ok' => true]);
    }

    public function status(Channel $channel): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $session = $channel->whatsappWebSession()->first();

        return response()->json([
            'status' => $session?->status ?? WhatsAppWebSession::STATUS_DISCONNECTED,
            'qr_raw' => $session?->qr_raw,
            'qr_generated_at' => $session?->qr_generated_at?->toIso8601String(),
            'connected_phone' => $session?->connected_phone,
            'connected_name' => $session?->connected_name,
            'last_connected_at' => $session?->last_connected_at?->toIso8601String(),
        ]);
    }

    public function logout(Channel $channel): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->service->logout($channel);

        return response()->json(['ok' => true]);
    }

    public function show(Channel $channel)
    {
        $this->ensureWebChannel($channel);

        $session = $channel->whatsappWebSession;
        if (!$session || $session->status !== WhatsAppWebSession::STATUS_CONNECTED) {
            return redirect()->route('whatsapp-directo.pair', $channel);
        }

        return view('whatsapp-directo.show', [
            'channel' => $channel,
            'session' => $session,
        ]);
    }

    public function conversations(Channel $channel): JsonResponse
    {
        $this->ensureWebChannel($channel);

        $conversations = Conversation::where('channel_id', $channel->id)
            ->with(['messages' => fn($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(fn(Conversation $c) => [
                'id' => $c->id,
                'contact_name' => $c->contact_name,
                'contact_identifier' => $c->contact_identifier,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
                'last_message_preview' => $c->messages->first()?->body,
                'last_message_direction' => $c->messages->first()?->direction,
                'unread_count' => $c->unread_count,
                'status' => $c->status,
            ]);

        return response()->json(['conversations' => $conversations]);
    }

    public function conversation(Channel $channel, Conversation $conversation): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->ensureConversationBelongs($channel, $conversation);

        $messages = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->map(fn(Message $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'type' => $m->type,
                'body' => $m->body,
                'created_at' => $m->created_at->toIso8601String(),
                'metadata' => $m->metadata,
            ]);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'contact_name' => $conversation->contact_name,
                'contact_identifier' => $conversation->contact_identifier,
                'status' => $conversation->status,
                'unread_count' => $conversation->unread_count,
            ],
            'messages' => $messages,
        ]);
    }

    public function send(Channel $channel, Conversation $conversation, Request $request): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->ensureConversationBelongs($channel, $conversation);

        $data = $request->validate([
            'body' => 'required|string|max:4000',
        ]);

        $message = Message::create([
            'organization_id' => $channel->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'type' => 'text',
            'direction' => 'outbound',
            'metadata' => ['wa_web' => true, 'pending' => true],
        ]);

        $ref = $this->service->sendText($channel, $conversation->contact_identifier, $data['body']);

        $message->update([
            'metadata' => array_merge($message->metadata ?? [], ['send_ref' => $ref]),
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'direction' => 'outbound',
                'type' => 'text',
                'body' => $message->body,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    }

    public function markAsRead(Channel $channel, Conversation $conversation): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->ensureConversationBelongs($channel, $conversation);

        $conversation->update(['unread_count' => 0]);
        return response()->json(['ok' => true]);
    }

    private function ensureWebChannel(Channel $channel): void
    {
        abort_unless($channel->type === WhatsAppWebService::CHANNEL_TYPE, 404);
    }

    private function ensureConversationBelongs(Channel $channel, Conversation $conversation): void
    {
        abort_unless($conversation->channel_id === $channel->id, 404);
    }
}
