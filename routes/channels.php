<?php

use App\Models\Conversation;
use App\Models\User;
use App\Services\WebchatTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| All channels embed organization_id to enforce tenant isolation at the
| subscription level. A user from org A can never subscribe to org B channels.
|
*/

// Organization-wide channel: supervisors and admins see all activity
Broadcast::channel('organization.{orgId}', function (User $user, int $orgId) {
    return $user->organization_id === $orgId
        && $user->hasPermissionTo('conversations.view-all');
});

// Conversation-specific channel: assigned agent, supervisors/admins, or webchat visitor
Broadcast::channel('conversation.{orgId}.{convId}', function ($user, int $orgId, int $convId) {
    // Authenticated user (agent/supervisor/admin)
    if ($user instanceof User) {
        if ($user->organization_id !== $orgId) {
            return false;
        }

        if ($user->hasPermissionTo('conversations.view-all')) {
            return true;
        }

        $conversation = Conversation::find($convId);

        return $conversation && $conversation->assigned_user_id === $user->id;
    }

    return false;
});

// Personal notification channel: only the user themselves
Broadcast::channel('user.{orgId}.{userId}', function (User $user, int $orgId, int $userId) {
    return $user->organization_id === $orgId
        && $user->id === $userId;
});
