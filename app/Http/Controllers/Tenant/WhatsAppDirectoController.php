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
use App\Services\WhatsAppChatImportService;
use App\Services\WhatsAppWebService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    public function conversations(Channel $channel, Request $request): JsonResponse
    {
        $this->ensureWebChannel($channel);

        $query = Conversation::where('channel_id', $channel->id)
            // Oculta ruido (status@broadcast, etc.): solo conversaciones con un teléfono real.
            ->whereRaw("contact_identifier ~ '^[0-9]{10,15}$'");

        // Visibilidad por asignación: un agente sin 'conversations.view-all'
        // solo ve sus conversaciones asignadas.
        $user = $request->user();
        if (! $user->hasPermissionTo('conversations.view-all')) {
            $query->where('assigned_user_id', $user->id);
        }

        $conversations = $query
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
            ->with('attachments')
            ->orderBy('id')
            ->get()
            ->map(fn(Message $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'type' => $m->type,
                'body' => $m->body,
                'created_at' => $m->created_at->toIso8601String(),
                'metadata' => $m->metadata,
                'attachments' => $m->attachments->map(fn ($a) => [
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

        $failed = false;
        try {
            $waId = $this->service->sendText($channel, $conversation->contact_identifier, $data['body']);
            $message->update([
                'external_id' => $waId ?: null,
                'metadata' => array_merge($message->metadata ?? [], ['send_ref' => $waId]),
            ]);
        } catch (\Throwable $e) {
            $failed = true;
            Log::warning('WA Directo: falló el envío', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            $message->update([
                'metadata' => array_merge($message->metadata ?? [], ['send_failed' => true]),
            ]);
        }

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'failed' => $failed,
            'message' => [
                'id' => $message->id,
                'direction' => 'outbound',
                'type' => 'text',
                'body' => $message->body,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    }

    /** Envía un archivo (imagen, video, audio o documento) por WhatsApp Directo. */
    public function sendMedia(Channel $channel, Conversation $conversation, Request $request): JsonResponse
    {
        $this->ensureWebChannel($channel);
        $this->ensureConversationBelongs($channel, $conversation);

        $request->validate([
            'file' => 'required|file|max:16384', // 16 MB (límite de WhatsApp)
            'caption' => 'nullable|string|max:1024',
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $mediaType = $this->detectMediaType($mime); // image|video|audio|document
        $caption = $request->input('caption') ?: null;

        $message = Message::create([
            'organization_id' => $channel->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'body' => $caption,
            'type' => $mediaType === 'document' ? 'file' : $mediaType,
            'direction' => 'outbound',
            'metadata' => ['wa_web' => true, 'pending' => true],
        ]);

        // Subir a S3 (disco chatme-media) y registrar el adjunto como listo.
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $storagePath = MessageAttachment::storagePath(
            $channel->organization_id,
            'whatsapp_web',
            $message->id,
            $extension,
        );
        Storage::disk(MessageAttachment::mediaDisk())->put($storagePath, file_get_contents($file), 'private');

        $attachment = MessageAttachment::create([
            'organization_id' => $channel->organization_id,
            'message_id' => $message->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storagePath,
            'file_size' => $file->getSize(),
            'mime_type' => $mime,
            'media_type' => $mediaType,
            'status' => 'ready',
        ]);

        $failed = false;
        try {
            // Evolution descarga el archivo desde una URL firmada de S3 (15 min).
            $url = $attachment->url();
            if (! $url) {
                throw new \RuntimeException('No se pudo generar la URL del adjunto');
            }

            if ($mediaType === 'audio') {
                $waId = $this->service->sendAudio($channel, $conversation->contact_identifier, $url);
            } else {
                $waId = $this->service->sendMedia(
                    $channel,
                    $conversation->contact_identifier,
                    $mediaType,
                    $url,
                    $caption,
                    $file->getClientOriginalName(),
                );
            }

            $message->update([
                'external_id' => $waId ?: null,
                'metadata' => array_merge($message->metadata ?? [], ['send_ref' => $waId, 'pending' => false]),
            ]);
        } catch (\Throwable $e) {
            $failed = true;
            Log::warning('WA Directo: falló el envío de multimedia', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            $message->update([
                'metadata' => array_merge($message->metadata ?? [], ['send_failed' => true, 'pending' => false]),
            ]);
        }

        $conversation->update(['last_message_at' => now()]);

        return response()->json(['failed' => $failed, 'message_id' => $message->id]);
    }

    private function detectMediaType(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };
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

        // Visibilidad por asignación: un agente sin 'conversations.view-all'
        // solo puede reenviar a sus conversaciones asignadas.
        $user = $request->user();
        if (! $user->hasPermissionTo('conversations.view-all')) {
            $query->where('assigned_user_id', $user->id);
        }

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
                    $waId = $this->service->sendText($channel, $target->contact_identifier, $body);
                    $new->update([
                        'external_id' => $waId ?: null,
                        'metadata' => array_merge($new->metadata ?? [], ['send_ref' => $waId]),
                    ]);
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

    // --- Importador de historial (ZIP de WhatsApp Business, Fase 22.6) --------

    private const IMPORT_MAX_MESSAGES = 20000;
    private const IMPORT_TMP_DIR = 'wa-import';

    /**
     * Paso 1: analiza el archivo exportado (ZIP o .txt) SIN persistir nada y
     * devuelve un resumen (remitentes, totales, rango de fechas) + un token para
     * el paso de importación. Guarda el archivo en un temporal local.
     */
    public function importAnalyze(Channel $channel, Request $request, WhatsAppChatImportService $parser): JsonResponse
    {
        $this->ensureWebChannel($channel);

        $request->validate([
            'file' => 'required|file|max:65536', // 64 MB (alineado con nginx/PHP-FPM en prod)
            'day_first' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'zip');
        if (! in_array($ext, ['zip', 'txt'], true)) {
            return response()->json(['error' => 'Sube el ZIP exportado de WhatsApp (o el _chat.txt).'], 422);
        }

        $dayFirst = $request->boolean('day_first', true);

        try {
            $text = $this->readChatText($file->getRealPath(), $ext);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo leer el archivo: ' . $e->getMessage()], 422);
        }
        if ($text === null) {
            return response()->json(['error' => 'No se encontró el chat (_chat.txt) dentro del ZIP.'], 422);
        }

        $messages = $parser->parse($text, $dayFirst);
        if (empty($messages)) {
            return response()->json(['error' => 'No se detectaron mensajes en el archivo.'], 422);
        }

        $token = Str::random(40);
        $file->storeAs(self::IMPORT_TMP_DIR, $token . '.' . $ext, 'local');

        return response()->json([
            'token' => $token,
            'summary' => $parser->summarize($messages),
            'suggested_me' => $channel->whatsappWebSession?->connected_name,
            'day_first' => $dayFirst,
            'truncated' => count($messages) > self::IMPORT_MAX_MESSAGES,
        ]);
    }

    /**
     * Paso 2: importa el historial al destino elegido. Idempotente por
     * external_id (`wa-import:<hash>`): reimportar el mismo archivo no duplica.
     */
    public function import(Channel $channel, Request $request, WhatsAppChatImportService $parser): JsonResponse
    {
        $this->ensureWebChannel($channel);

        $data = $request->validate([
            'token' => 'required|string|max:64',
            'me_sender' => 'nullable|string|max:120',
            'day_first' => 'nullable|boolean',
            'conversation_id' => 'nullable|integer',
            'phone' => ['nullable', 'string', 'regex:/^\+?\d{10,15}$/'],
            'contact_name' => 'nullable|string|max:255',
        ]);

        // Localiza el temporal (sanea el token para evitar path traversal).
        $token = preg_replace('/[^A-Za-z0-9]/', '', $data['token']);
        $path = null;
        $ext = null;
        foreach (['zip', 'txt'] as $e) {
            $candidate = self::IMPORT_TMP_DIR . '/' . $token . '.' . $e;
            if (Storage::disk('local')->exists($candidate)) {
                $path = $candidate;
                $ext = $e;
                break;
            }
        }
        if (! $path) {
            return response()->json(['error' => 'La sesión de importación expiró. Vuelve a subir el archivo.'], 422);
        }

        $conversation = $this->resolveImportTarget($channel, $data);
        if (! $conversation) {
            return response()->json(['error' => 'Indica una conversación existente o un teléfono + nombre.'], 422);
        }

        $absPath = Storage::disk('local')->path($path);
        $dayFirst = $request->boolean('day_first', true);

        $text = $this->readChatText($absPath, $ext);
        if ($text === null) {
            return response()->json(['error' => 'No se pudo leer el chat del archivo.'], 422);
        }

        $messages = array_slice($parser->parse($text, $dayFirst), 0, self::IMPORT_MAX_MESSAGES);
        $meSender = trim((string) ($data['me_sender'] ?? ''));

        // Índice de multimedia del ZIP por nombre base.
        $zip = null;
        $entryByBase = [];
        if ($ext === 'zip') {
            $zip = new \ZipArchive();
            if ($zip->open($absPath) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryByBase[basename((string) $zip->getNameIndex($i))] = $i;
                }
            } else {
                $zip = null;
            }
        }

        $imported = 0;
        $skipped = 0;
        $mediaImported = 0;
        $latestTs = null;

        foreach ($messages as $m) {
            $ts = $m['ts'] ?? now();
            $externalId = 'wa-import:' . substr(sha1(
                $conversation->id . '|' . $ts->toIso8601String() . '|' . $m['sender'] . '|' . $m['body'] . '|' . ($m['media_file'] ?? '')
            ), 0, 40);

            $exists = Message::withoutGlobalScopes()
                ->where('organization_id', $channel->organization_id)
                ->where('external_id', $externalId)
                ->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            $direction = ($meSender !== '' && $m['sender'] === $meSender) ? 'outbound' : 'inbound';

            $type = 'text';
            $body = $m['body'];
            $mediaType = null;
            $mediaContent = null;
            $mediaName = $m['media_file'];

            if ($mediaName) {
                $mediaType = $parser->mediaTypeFor($mediaName);
                $type = match ($mediaType) {
                    'document' => 'file',
                    'sticker', 'image' => 'image',
                    default => $mediaType,
                };

                if ($zip) {
                    $base = basename($mediaName);
                    if (isset($entryByBase[$base])) {
                        $raw = $zip->getFromIndex($entryByBase[$base]);
                        if ($raw !== false && strlen($raw) <= 16 * 1024 * 1024) {
                            $mediaContent = $raw;
                        }
                    }
                }

                if (! $mediaContent) {
                    // Archivo no incluido en el export: queda el marcador de texto.
                    $body = $this->mediaPlaceholder($mediaType);
                }
            } elseif ($m['is_media_omitted']) {
                $body = '[Multimedia]';
            }

            $message = new Message([
                'organization_id' => $channel->organization_id,
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'body' => $body,
                'type' => $type,
                'direction' => $direction,
                'external_id' => $externalId,
                'metadata' => [
                    'wa_web' => true,
                    'imported' => true,
                    'from_phone' => $direction === 'outbound',
                    'original_sender' => $m['sender'],
                ],
            ]);
            // created_at = fecha original (orden cronológico correcto en el chat).
            $message->created_at = $ts;
            $message->updated_at = $ts;
            $message->save();
            $imported++;

            if ($latestTs === null || $ts->gt($latestTs)) {
                $latestTs = $ts;
            }

            if ($mediaContent && $mediaName) {
                $extn = pathinfo($mediaName, PATHINFO_EXTENSION) ?: 'bin';
                $sp = MessageAttachment::storagePath($channel->organization_id, 'whatsapp_web', $message->id, $extn);
                Storage::disk(MessageAttachment::mediaDisk())->put($sp, $mediaContent, 'private');

                MessageAttachment::create([
                    'organization_id' => $channel->organization_id,
                    'message_id' => $message->id,
                    'file_name' => $mediaName,
                    'file_path' => $sp,
                    'file_size' => strlen($mediaContent),
                    'mime_type' => $parser->mimeFor($mediaName),
                    'media_type' => $mediaType,
                    'status' => 'ready',
                ]);
                $mediaImported++;
            }
        }

        $zip?->close();
        Storage::disk('local')->delete($path);

        if ($latestTs && (! $conversation->last_message_at || $latestTs->gt($conversation->last_message_at))) {
            $conversation->update(['last_message_at' => $latestTs]);
        }

        Log::info('WA Directo: historial importado', [
            'conversation_id' => $conversation->id,
            'imported' => $imported,
            'skipped' => $skipped,
            'media' => $mediaImported,
            'by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'imported' => $imported,
            'skipped' => $skipped,
            'media_imported' => $mediaImported,
        ]);
    }

    /** Resuelve la conversación destino: existente por id, o nueva por teléfono. */
    private function resolveImportTarget(Channel $channel, array $data): ?Conversation
    {
        if (! empty($data['conversation_id'])) {
            return Conversation::where('channel_id', $channel->id)->find($data['conversation_id']);
        }

        $phone = preg_replace('/\D/', '', (string) ($data['phone'] ?? ''));
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            return null;
        }
        $name = trim((string) ($data['contact_name'] ?? '')) ?: $phone;

        return Conversation::where('organization_id', $channel->organization_id)
            ->where('channel_id', $channel->id)
            ->where('contact_identifier', $phone)
            ->where('status', '!=', 'closed')
            ->first()
            ?? Conversation::create([
                'organization_id' => $channel->organization_id,
                'brand_id' => $channel->brand_id,
                'channel_id' => $channel->id,
                'contact_name' => $name,
                'contact_identifier' => $phone,
                'status' => 'open',
                'priority' => 'normal',
                'last_message_at' => now(),
            ]);
    }

    /** Lee el _chat.txt de un ZIP (o el propio .txt). */
    private function readChatText(string $absPath, string $ext): ?string
    {
        if ($ext === 'txt') {
            $content = @file_get_contents($absPath);
            return $content !== false ? $content : null;
        }

        $zip = new \ZipArchive();
        if ($zip->open($absPath) !== true) {
            throw new \RuntimeException('No se pudo abrir el ZIP.');
        }

        $index = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_ends_with(strtolower((string) $zip->getNameIndex($i)), '_chat.txt')) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with(strtolower((string) $zip->getNameIndex($i)), '.txt')) {
                    $index = $i;
                    break;
                }
            }
        }

        $content = $index !== null ? $zip->getFromIndex($index) : false;
        $zip->close();

        return $content !== false ? $content : null;
    }

    private function mediaPlaceholder(string $mediaType): string
    {
        return match ($mediaType) {
            'image' => '[Imagen]',
            'video' => '[Video]',
            'audio' => '[Audio]',
            'sticker' => '[Sticker]',
            default => '[Documento]',
        };
    }

    private function ensureWebChannel(Channel $channel): void
    {
        abort_unless($channel->type === WhatsAppWebService::CHANNEL_TYPE, 404);
    }

    private function ensureConversationBelongs(Channel $channel, Conversation $conversation): void
    {
        abort_unless($conversation->channel_id === $channel->id, 404);

        // Visibilidad por asignación: un agente sin 'conversations.view-all'
        // solo puede acceder a las conversaciones asignadas a él. Las demás
        // desaparecen de su bandeja (el admin/supervisor las asigna).
        $user = request()->user();
        if ($user && ! $user->hasPermissionTo('conversations.view-all')) {
            abort_unless($conversation->assigned_user_id === $user->id, 403);
        }
    }
}
