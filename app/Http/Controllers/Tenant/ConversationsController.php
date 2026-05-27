<?php

namespace App\Http\Controllers\Tenant;

use App\Events\ConversationAssignedEvent;
use App\Events\ConversationClosedEvent;
use App\Events\ConversationTransferredEvent;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationAssignment;
use App\Models\ConversationTransfer;
use App\Models\MessageAttachment;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\DealService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ConversationsController extends Controller
{
    use AuthorizesRequests;
    public function show(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $conversation->load(['channel', 'assignedUser', 'branch', 'deals']);

        // Redirige si la conversación pertenece a un canal WhatsApp Directo —
        // tiene su propia UI/endpoint en /whatsapp-directo.
        if ($conversation->channel?->type === 'whatsapp_web') {
            if ($request->wantsJson()) {
                return response()->json([
                    'redirect' => route('whatsapp-directo.show', $conversation->channel_id),
                ], 409);
            }
            return redirect()->route('whatsapp-directo.show', $conversation->channel_id);
        }

        $messages = $conversation->messages()
            ->with(['user', 'attachments'])
            ->oldest()
            ->get();

        $agents = User::where('organization_id', $request->user()->organization_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // JSON response for AJAX chat panel
        if ($request->wantsJson()) {
            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'organization_id' => $conversation->organization_id,
                    'contact_name' => $conversation->contact_name,
                    'contact_identifier' => $conversation->contact_identifier,
                    'channel_id' => $conversation->channel_id,
                    'channel_type' => $conversation->channel->type,
                    'channel_name' => $conversation->channel->name,
                    'status' => $conversation->status,
                    'priority' => $conversation->priority,
                    'subject' => $conversation->subject,
                    'assigned_user' => $conversation->assignedUser?->name ?? 'Sin asignar',
                    'is_open' => $conversation->isOpen(),
                    'created_at' => $conversation->created_at->format('M d, Y H:i'),
                    'close_url' => route('inbox.conversations.close', $conversation),
                    'reopen_url' => route('inbox.conversations.reopen', $conversation),
                    'assign_url' => route('inbox.conversations.assign', $conversation),
                    'send_url' => route('inbox.conversations.messages.store', $conversation),
                    'poll_url' => route('inbox.conversations.messages.poll', $conversation),
                    'can_delete' => $request->user()->can('delete', $conversation),
                    'can_create_deal' => $request->user()->can('create', \App\Models\Deal::class),
                    'has_deal' => $conversation->deals()->exists(),
                    'convert_to_deal_url' => route('inbox.conversations.convert-to-deal', $conversation),
                    'whatsapp_24h_expired' => $conversation->channel->isWhatsApp()
                        && !$conversation->messages()
                            ->where('direction', 'inbound')
                            ->where('created_at', '>=', now()->subHours(24))
                            ->exists(),
                ],
                'messages' => $messages->map(fn ($m) => [
                    'id' => $m->id,
                    'body' => $m->body,
                    'direction' => $m->direction,
                    'type' => $m->type,
                    'is_forwarded' => (bool) (($m->metadata ?? [])['forwarded'] ?? false),
                    'user_name' => $m->user?->name ?? 'Agent',
                    'time' => $m->created_at->format('H:i'),
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
                ]),
                'agents' => $agents,
            ]);
        }

        // Load conversation list for desktop sidebar
        $user = $request->user();
        $listQuery = Conversation::with(['channel', 'assignedUser', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->latest('last_message_at')
            // Los canales whatsapp_web (WhatsApp Directo) tienen su propia sección
            // en /whatsapp-directo. Excluirlos aquí para no mezclarlos con el inbox
            // normal (mismo criterio que InboxController::index()).
            ->whereHas('channel', fn ($q) => $q->where('type', '!=', 'whatsapp_web'));

        if (! $user->hasPermissionTo('conversations.view-all')) {
            $listQuery->where('assigned_user_id', $user->id);
        }

        $conversations = $listQuery->paginate(25)->withQueryString();

        return view('inbox.show', compact('conversation', 'messages', 'agents', 'conversations'));
    }

    public function markAsRead(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $conversation->update(['unread_count' => 0]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function close(Request $request, Conversation $conversation)
    {
        $this->authorize('close', $conversation);

        $conversation->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        ConversationClosedEvent::dispatch($conversation, $request->user()->id);

        return back()->with('success', 'Conversation closed.');
    }

    public function reopen(Conversation $conversation)
    {
        $this->authorize('reopen', $conversation);

        $conversation->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        return back()->with('success', 'Conversation reopened.');
    }

    public function assign(Request $request, Conversation $conversation)
    {
        $this->authorize('assign', $conversation);

        $request->validate([
            'assigned_user_id' => 'required|exists:users,id',
        ]);

        $assignee = User::findOrFail($request->input('assigned_user_id'));

        if ($assignee->organization_id !== $request->user()->organization_id) {
            abort(403, 'Cannot assign to user from another organization.');
        }

        // Close previous assignment
        $conversation->assignments()
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        $conversation->update(['assigned_user_id' => $assignee->id]);

        ConversationAssignment::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => $assignee->id,
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        ConversationAssignedEvent::dispatch($conversation->fresh(), $assignee->id);

        return back()->with('success', "Assigned to {$assignee->name}.");
    }

    public function destroy(Request $request, Conversation $conversation)
    {
        $this->authorize('delete', $conversation);

        // Delete media files from storage, but only if no other attachment shares the same file
        $attachments = MessageAttachment::withoutGlobalScopes()
            ->whereIn('message_id', $conversation->messages()->select('id'))
            ->whereNotNull('file_path')
            ->get(['id', 'file_path', 'thumbnail_path']);

        $disk = MessageAttachment::mediaDisk();
        $messageIds = $conversation->messages()->pluck('id')->toArray();

        foreach ($attachments as $att) {
            try {
                // Only delete from storage if no OTHER attachment references the same file
                if ($att->file_path) {
                    $otherExists = MessageAttachment::withoutGlobalScopes()
                        ->where('file_path', $att->file_path)
                        ->where('id', '!=', $att->id)
                        ->whereNotIn('message_id', $messageIds)
                        ->exists();
                    if (!$otherExists) {
                        Storage::disk($disk)->delete($att->file_path);
                    }
                }
                if ($att->thumbnail_path) {
                    $otherThumbExists = MessageAttachment::withoutGlobalScopes()
                        ->where('thumbnail_path', $att->thumbnail_path)
                        ->where('id', '!=', $att->id)
                        ->whereNotIn('message_id', $messageIds)
                        ->exists();
                    if (!$otherThumbExists) {
                        Storage::disk($disk)->delete($att->thumbnail_path);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to delete attachment file', ['path' => $att->file_path, 'error' => $e->getMessage()]);
            }
        }

        Log::info('Conversation deleted', [
            'conversation_id' => $conversation->id,
            'contact' => $conversation->contact_identifier,
            'deleted_by' => $request->user()->id,
        ]);

        $conversation->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('inbox')->with('success', 'Conversación eliminada.');
    }

    public function transfer(Request $request, Conversation $conversation)
    {
        $this->authorize('transfer', $conversation);

        $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $toUser = User::findOrFail($request->input('to_user_id'));

        if ($toUser->organization_id !== $request->user()->organization_id) {
            abort(403, 'Cannot transfer to user from another organization.');
        }

        $fromUserId = $conversation->assigned_user_id;

        // Close previous assignment
        $conversation->assignments()
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        $conversation->update(['assigned_user_id' => $toUser->id]);

        ConversationTransfer::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'from_user_id' => $fromUserId ?? $request->user()->id,
            'to_user_id' => $toUser->id,
            'transferred_by' => $request->user()->id,
            'reason' => $request->input('reason'),
            'transferred_at' => now(),
        ]);

        ConversationAssignment::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => $toUser->id,
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        ConversationTransferredEvent::dispatch(
            $conversation->fresh(),
            $fromUserId ?? $request->user()->id,
            $toUser->id,
        );

        return back()->with('success', "Transferred to {$toUser->name}.");
    }

    public function convertToDeal(Request $request, Conversation $conversation, DealService $dealService)
    {
        $this->authorize('view', $conversation);

        $validated = $request->validate([
            'contact_name' => 'required|string|max:255',
            'expected_close_date' => 'nullable|date',
        ]);

        $user = $request->user();
        $orgId = $user->organization_id;

        // Find default pipeline
        $pipeline = Pipeline::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('is_default', true)
            ->first()
            ?? Pipeline::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->first();

        if (! $pipeline) {
            $error = 'No hay pipeline configurado.';
            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }
            return back()->with('error', $error);
        }

        // Find "Contactado" stage, fallback to second stage or first
        $contactadoStage = $pipeline->stages()
            ->where('name', 'Contactado')
            ->first()
            ?? $pipeline->stages()->orderBy('position')->skip(1)->first()
            ?? $pipeline->stages()->orderBy('position')->first();

        $deal = $dealService->createDeal([
            'organization_id' => $orgId,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $contactadoStage->id,
            'contact_name' => $validated['contact_name'],
            'contact_phone' => $conversation->contact_identifier,
            'value' => 0,
            'assigned_user_id' => $user->id,
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'conversation_id' => $conversation->id,
        ], $user);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Negocio creado: {$deal->contact_name}",
                'deal_url' => route('deals.show', $deal),
            ]);
        }

        return back()->with('success', "Negocio \"{$deal->contact_name}\" creado en etapa {$contactadoStage->name}.");
    }
}
