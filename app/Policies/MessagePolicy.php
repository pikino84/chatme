<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MessagePolicy extends TenantPolicy
{
    public function view(User $user, Model $message): bool
    {
        if (!$this->belongsToSameOrganization($user, $message)) {
            return false;
        }

        if (!$user->hasPermissionTo('messages.view')) {
            return false;
        }

        if ($user->hasPermissionTo('conversations.view-all')) {
            return true;
        }

        return $message->conversation->assigned_user_id === $user->id;
    }

    public function send(User $user, Model $conversation): bool
    {
        if (!$this->belongsToSameOrganization($user, $conversation)) {
            return false;
        }

        if ($user->hasPermissionTo('conversations.view-all')) {
            return $user->hasPermissionTo('messages.send');
        }

        return $user->hasPermissionTo('messages.send')
            && $conversation->assigned_user_id === $user->id;
    }

    public function sendInternalNote(User $user, Model $conversation): bool
    {
        if (!$this->belongsToSameOrganization($user, $conversation)) {
            return false;
        }

        return $user->hasPermissionTo('messages.internal-note');
    }

    public function delete(User $user, Model $message): bool
    {
        return $this->belongsToSameOrganization($user, $message)
            && $user->hasPermissionTo('messages.delete');
    }

    public function update(User $user, Model $message): bool
    {
        return false;
    }
}
