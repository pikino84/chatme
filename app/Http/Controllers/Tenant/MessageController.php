<?php

namespace App\Http\Controllers\Tenant;

use App\Events\MessageReceivedEvent;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    use AuthorizesRequests;
    public function poll(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $afterId = (int) $request->query('after_id', 0);

        $messages = $conversation->messages()
            ->with('user')
            ->when($afterId, fn ($q) => $q->where('id', '>', $afterId))
            ->oldest()
            ->limit(50)
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'body' => e($msg->body),
                'type' => $msg->type,
                'direction' => $msg->direction,
                'user_name' => $msg->user?->name ?? ($msg->isInbound() ? null : 'Agent'),
                'time' => $msg->created_at->format('H:i'),
            ]);

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, Conversation $conversation)
    {
        $type = $request->input('type', 'text');

        if ($type === 'internal_note') {
            $this->authorize('sendInternalNote', [Message::class, $conversation]);
        } else {
            $this->authorize('send', [Message::class, $conversation]);
        }

        $request->validate([
            'body' => 'required|string|max:5000',
            'type' => 'nullable|in:text,internal_note',
        ]);

        $body = strip_tags($request->input('body'));

        $message = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'body' => $body,
            'type' => $type,
            'direction' => 'outbound',
        ]);

        $conversation->update(['last_message_at' => now()]);

        MessageReceivedEvent::dispatch($message);

        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 201);
        }

        return back();
    }
}
