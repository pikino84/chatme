<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ConversationPolicy extends TenantPolicy
{
    public function view(User $user, Model $conversation): bool
    {
        if (!$this->belongsToSameOrganization($user, $conversation)) {
            return false;
        }

        if ($user->hasPermissionTo('conversations.view-all')) {
            return true;
        }

        return $user->hasPermissionTo('conversations.view')
            && $conversation->assigned_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('conversations.create');
    }

    public function close(User $user, Model $conversation): bool
    {
        if (!$this->belongsToSameOrganization($user, $conversation)) {
            return false;
        }

        if ($user->hasPermissionTo('conversations.view-all')) {
            return true;
        }

        return $user->hasPermissionTo('conversations.close')
            && $conversation->assigned_user_id === $user->id;
    }

    public function reopen(User $user, Model $conversation): bool
    {
        return $this->belongsToSameOrganization($user, $conversation)
            && $user->hasPermissionTo('conversations.reopen');
    }

    public function assign(User $user, Model $conversation): bool
    {
        return $this->belongsToSameOrganization($user, $conversation)
            && $user->hasPermissionTo('conversations.assign');
    }

    public function transfer(User $user, Model $conversation): bool
    {
        if (!$this->belongsToSameOrganization($user, $conversation)) {
            return false;
        }

        if ($user->hasPermissionTo('conversations.transfer')) {
            return true;
        }

        return $conversation->assigned_user_id === $user->id;
    }

    public function update(User $user, Model $conversation): bool
    {
        return false;
    }

    public function delete(User $user, Model $conversation): bool
    {
        return false;
    }
}
