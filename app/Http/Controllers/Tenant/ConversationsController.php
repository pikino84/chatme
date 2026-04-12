<?php

namespace App\Http\Controllers\Tenant;

use App\Events\ConversationAssignedEvent;
use App\Events\ConversationClosedEvent;
use App\Events\ConversationTransferredEvent;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationAssignment;
use App\Models\ConversationTransfer;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ConversationsController extends Controller
{
    use AuthorizesRequests;
    public function show(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $conversation->load(['channel', 'assignedUser', 'branch']);
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
            ->latest('last_message_at');

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
}
