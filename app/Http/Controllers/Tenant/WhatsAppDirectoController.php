<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Pipeline;
use App\Models\Tag;
use App\Models\User;
use App\Models\WhatsAppWebSession;
use App\Services\DealService;
use App\Services\WhatsAppWebService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
            // Oculta ruido (status@broadcast, etc.): solo conversaciones con un teléfono real.
            ->whereRaw("contact_identifier ~ '^[0-9]{10,15}$'")
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

    /**
     * Datos para el panel de info del contacto (acciones de la bandeja + convertir a negocio).
     */
    public function info(Channel $channel, Conversation $conversation, Request $request): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->ensureConversationBelongs($channel, $conversation);

        $user = $request->user();

        $agents = User::where('organization_id', $channel->organization_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $deals = $conversation->deals()
            ->select('deals.id', 'deals.contact_name', 'deals.value')
            ->get()
            ->map(fn (Deal $d) => [
                'id' => $d->id,
                'contact_name' => $d->contact_name,
                'value' => (float) $d->value,
                'url' => route('deals.show', $d),
            ]);

        $pipelineExists = Pipeline::withoutGlobalScopes()
            ->where('organization_id', $channel->organization_id)
            ->exists();

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'contact_name' => $conversation->contact_name,
                'contact_identifier' => $conversation->contact_identifier,
                'status' => $conversation->status,
                'assigned_user_id' => $conversation->assigned_user_id,
                'assigned_user_name' => $conversation->assignedUser?->name,
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
            'agents' => $agents,
            'deals' => $deals,
            'can' => [
                'create_deal' => $user->can('create', Deal::class) && $pipelineExists,
                'close' => $user->can('close', $conversation),
                'reopen' => $user->can('reopen', $conversation),
                'assign' => $user->can('assign', $conversation),
            ],
        ]);
    }

    public function convertToDeal(Channel $channel, Conversation $conversation, Request $request, DealService $dealService): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->ensureConversationBelongs($channel, $conversation);
        $this->authorize('create', Deal::class);

        $data = $request->validate([
            'contact_name' => 'required|string|max:255',
            'expected_close_date' => 'nullable|date',
        ]);

        $user = $request->user();
        $orgId = $channel->organization_id;

        $pipeline = Pipeline::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('is_default', true)
            ->first()
            ?? Pipeline::withoutGlobalScopes()->where('organization_id', $orgId)->first();

        if (! $pipeline) {
            return response()->json(['error' => 'No hay pipeline configurado.'], 422);
        }

        $stage = $pipeline->stages()->where('name', 'Contactado')->first()
            ?? $pipeline->stages()->orderBy('position')->skip(1)->first()
            ?? $pipeline->stages()->orderBy('position')->first();

        $deal = $dealService->createDeal([
            'organization_id' => $orgId,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'contact_name' => $data['contact_name'],
            'contact_phone' => $conversation->contact_identifier,
            'value' => 0,
            'assigned_user_id' => $user->id,
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'conversation_id' => $conversation->id,
        ], $user);

        // Auto-etiqueta "whatsapp-directo" para poder filtrar estos leads en el CRM.
        try {
            $tag = Tag::firstOrCreate(
                ['organization_id' => $orgId, 'name' => 'whatsapp-directo'],
                ['color' => '#25D366'],
            );
            $deal->tags()->syncWithoutDetaching([$tag->id]);
        } catch (\Throwable $e) {
            Log::warning('WA Directo: no se pudo auto-etiquetar el negocio', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => "Negocio creado: {$deal->contact_name}",
            'deal' => [
                'id' => $deal->id,
                'contact_name' => $deal->contact_name,
                'value' => (float) $deal->value,
                'url' => route('deals.show', $deal),
            ],
        ]);
    }

    public function close(Channel $channel, Conversation $conversation): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->ensureConversationBelongs($channel, $conversation);
        $this->authorize('close', $conversation);

        $conversation->update(['status' => 'closed', 'closed_at' => now()]);

        return response()->json(['ok' => true, 'status' => 'closed']);
    }

    public function reopen(Channel $channel, Conversation $conversation): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->ensureConversationBelongs($channel, $conversation);
        $this->authorize('reopen', $conversation);

        $conversation->update(['status' => 'open', 'closed_at' => null]);

        return response()->json(['ok' => true, 'status' => 'open']);
    }

    public function assign(Channel $channel, Conversation $conversation, Request $request): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->ensureConversationBelongs($channel, $conversation);
        $this->authorize('assign', $conversation);

        $data = $request->validate([
            'assigned_user_id' => 'required|integer|exists:users,id',
        ]);

        $assignee = User::findOrFail($data['assigned_user_id']);
        abort_if($assignee->organization_id !== $channel->organization_id, 403);

        $conversation->update(['assigned_user_id' => $assignee->id]);

        return response()->json(['ok' => true, 'assigned_user_id' => $assignee->id, 'assigned_user_name' => $assignee->name]);
    }

    public function deleteMessages(Channel $channel, Conversation $conversation, Request $request): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->ensureConversationBelongs($channel, $conversation);
        $this->authorize('view', $conversation);

        $data = $request->validate([
            'message_ids' => 'required|array|min:1|max:200',
            'message_ids.*' => 'integer',
        ]);

        $messageIds = $conversation->messages()
            ->whereIn('id', $data['message_ids'])
            ->pluck('id')
            ->toArray();

        if (empty($messageIds)) {
            return response()->json(['error' => 'No se encontraron mensajes.'], 422);
        }

        // Limpiar archivos adjuntos del storage si ningún otro mensaje los referencia.
        $attachments = MessageAttachment::withoutGlobalScopes()
            ->whereIn('message_id', $messageIds)
            ->whereNotNull('file_path')
            ->get(['id', 'file_path', 'thumbnail_path']);
        $disk = MessageAttachment::mediaDisk();

        foreach ($attachments as $att) {
            try {
                if ($att->file_path && ! MessageAttachment::withoutGlobalScopes()
                        ->where('file_path', $att->file_path)->where('id', '!=', $att->id)
                        ->whereNotIn('message_id', $messageIds)->exists()) {
                    Storage::disk($disk)->delete($att->file_path);
                }
                if ($att->thumbnail_path && ! MessageAttachment::withoutGlobalScopes()
                        ->where('thumbnail_path', $att->thumbnail_path)->where('id', '!=', $att->id)
                        ->whereNotIn('message_id', $messageIds)->exists()) {
                    Storage::disk($disk)->delete($att->thumbnail_path);
                }
            } catch (\Throwable $e) {
                Log::warning('WA Directo: no se pudo borrar adjunto', ['path' => $att->file_path, 'error' => $e->getMessage()]);
            }
        }

        $deleted = Message::whereIn('id', $messageIds)->delete();

        Log::info('WA Directo: mensajes borrados', [
            'conversation_id' => $conversation->id,
            'count' => $deleted,
            'by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    /**
     * Conversaciones del mismo canal que pueden recibir un reenvío (picker del modal).
     */
    public function forwardTargets(Channel $channel, Request $request): JsonResponse
    {
        $this->ensureWebChannel($channel);

        $q = trim((string) $request->query('q', ''));
        $query = Conversation::where('channel_id', $channel->id)
            ->orderByDesc('last_message_at')
            ->limit(50);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('contact_name', 'like', "%{$q}%")
                  ->orWhere('contact_identifier', 'like', "%{$q}%");
            });
        }

        return response()->json([
            'targets' => $query->get(['id', 'contact_name', 'contact_identifier'])->map(fn (Conversation $c) => [
                'id' => $c->id,
                'contact_name' => $c->contact_name,
                'contact_identifier' => $c->contact_identifier,
            ]),
        ]);
    }

    public function forwardMessages(Channel $channel, Conversation $conversation, Request $request): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->ensureConversationBelongs($channel, $conversation);
        $this->authorize('view', $conversation);

        $data = $request->validate([
            'message_ids' => 'required|array|min:1|max:25',
            'message_ids.*' => 'integer',
            'conversation_ids' => 'nullable|array|max:10',
            'conversation_ids.*' => 'integer',
            'phones' => 'nullable|array|max:10',
            'phones.*' => ['string', 'regex:/^\+?\d{10,15}$/'],
        ]);

        if (empty($data['conversation_ids']) && empty($data['phones'])) {
            return response()->json(['error' => 'Selecciona al menos un destino.'], 422);
        }

        $session = $channel->whatsappWebSession;
        if (! $session || $session->status !== WhatsAppWebSession::STATUS_CONNECTED) {
            return response()->json(['error' => 'El número no está conectado.'], 422);
        }

        $messages = $conversation->messages()
            ->whereIn('id', $data['message_ids'])
            ->where('type', '!=', 'internal_note')
            ->orderBy('id')
            ->get();

        if ($messages->isEmpty()) {
            return response()->json(['error' => 'No se encontraron mensajes válidos.'], 422);
        }

        // Resolver destinos: phone => conversation_id|null
        $targets = [];
        foreach (($data['conversation_ids'] ?? []) as $cid) {
            $target = Conversation::where('channel_id', $channel->id)->find($cid);
            if ($target) {
                $targets[$target->contact_identifier] = $target->id;
            }
        }
        foreach (($data['phones'] ?? []) as $phone) {
            $phone = preg_replace('/\D/', '', $phone);
            if ($phone !== '' && ! array_key_exists($phone, $targets)) {
                $targets[$phone] = null;
            }
        }
        if (empty($targets)) {
            return response()->json(['error' => 'Destinos inválidos.'], 422);
        }

        $forwarded = 0;
        foreach ($targets as $phone => $cid) {
            $target = $cid ? Conversation::find($cid) : null;
            if (! $target) {
                $target = Conversation::where('organization_id', $channel->organization_id)
                    ->where('channel_id', $channel->id)
                    ->where('contact_identifier', $phone)
                    ->where('status', '!=', 'closed')
                    ->first();
            }
            if (! $target) {
                $target = Conversation::create([
                    'organization_id' => $channel->organization_id,
                    'brand_id' => $channel->brand_id,
                    'channel_id' => $channel->id,
                    'contact_name' => $phone,
                    'contact_identifier' => $phone,
                    'status' => 'open',
                    'priority' => 'normal',
                    'last_message_at' => now(),
                ]);
            }

            foreach ($messages as $m) {
                $body = $m->body ?: '[archivo reenviado]';
                $new = Message::create([
                    'organization_id' => $channel->organization_id,
                    'conversation_id' => $target->id,
                    'user_id' => $request->user()->id,
                    'body' => $body,
                    'type' => 'text',
                    'direction' => 'outbound',
                    'metadata' => ['wa_web' => true, 'forwarded' => true, 'pending' => true],
                ]);
                try {
                    $ref = $this->service->sendText($channel, $target->contact_identifier, $body);
                    $new->update(['metadata' => array_merge($new->metadata ?? [], ['send_ref' => $ref])]);
                    $forwarded++;
                } catch (\Throwable $e) {
                    Log::warning('WA Directo: falló reenvío', ['to' => $target->contact_identifier, 'error' => $e->getMessage()]);
                }
            }

            $target->update(['last_message_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'forwarded' => $forwarded,
            'targets' => count($targets),
        ]);
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
