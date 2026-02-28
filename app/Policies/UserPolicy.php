<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasPermissionTo('users.view');
    }

    public function view(User $authUser, User $targetUser): bool
    {
        if ($authUser->id === $targetUser->id) {
            return true;
        }

        return $authUser->organization_id === $targetUser->organization_id
            && $authUser->hasPermissionTo('users.view');
    }

    public function create(User $authUser): bool
    {
        return $authUser->organization_id !== null
            && $authUser->hasPermissionTo('users.create');
    }

    public function update(User $authUser, User $targetUser): bool
    {
        if ($authUser->id === $targetUser->id) {
            return true;
        }

        return $authUser->organization_id === $targetUser->organization_id
            && $authUser->hasPermissionTo('users.update');
    }

    public function delete(User $authUser, User $targetUser): bool
    {
        if ($authUser->id === $targetUser->id) {
            return false;
        }

        return $authUser->organization_id === $targetUser->organization_id
            && $authUser->hasPermissionTo('users.delete');
    }
}
