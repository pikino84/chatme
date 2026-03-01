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

        $message = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'body' => $request->input('body'),
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
