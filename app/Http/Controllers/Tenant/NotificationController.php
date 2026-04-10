<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        // Unread conversations count
        $query = Conversation::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('status', '!=', 'closed')
            ->where('unread_count', '>', 0);

        if (!$user->hasPermissionTo('conversations.view-all')) {
            $query->where('assigned_user_id', $user->id);
        }

        $unreadConversations = $query->count();

        // Recent unread conversations (for toast notifications)
        $sinceParam = $request->query('since');
        $recentUnread = [];

        if ($sinceParam) {
            $since = \Carbon\Carbon::parse($sinceParam);

            $recentQuery = Conversation::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->where('status', '!=', 'closed')
                ->where('unread_count', '>', 0)
                ->where('last_message_at', '>', $since);

            if (!$user->hasPermissionTo('conversations.view-all')) {
                $recentQuery->where('assigned_user_id', $user->id);
            }

            $recentUnread = $recentQuery
                ->select('id', 'contact_name', 'contact_identifier', 'unread_count', 'last_message_at')
                ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
                ->orderByDesc('last_message_at')
                ->limit(5)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'contact_name' => $c->contact_name ?: $c->contact_identifier,
                    'unread_count' => $c->unread_count,
                    'last_body' => $c->messages->first()?->body,
                ]);
        }

        // New deals (leads) count in last 24h
        $newDealsToday = Deal::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('status', 'open')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return response()->json([
            'unread_conversations' => $unreadConversations,
            'new_deals_today' => $newDealsToday,
            'recent_unread' => $recentUnread,
            'server_time' => now()->toISOString(),
        ]);
    }
}
