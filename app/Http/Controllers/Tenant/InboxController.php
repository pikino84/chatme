<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Conversation::with(['channel', 'assignedUser', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->latest('last_message_at');

        if (! $user->hasPermissionTo('conversations.view-all')) {
            $query->where('assigned_user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->input('channel_id'));
        }

        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->input('assigned_user_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'ilike', "%{$search}%")
                  ->orWhere('contact_identifier', 'ilike', "%{$search}%");
            });
        }

        $conversations = $query->paginate(25)->withQueryString();
        $channels = Channel::select('id', 'name', 'type')->get();
        $agents = User::where('organization_id', $user->organization_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('inbox.index', compact('conversations', 'channels', 'agents'));
    }
}
