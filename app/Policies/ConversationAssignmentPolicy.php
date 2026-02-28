<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ConversationAssignmentPolicy extends TenantPolicy
{
    public function view(User $user, Model $assignment): bool
    {
        return $this->belongsToSameOrganization($user, $assignment);
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('conversations.assign');
    }

    public function update(User $user, Model $assignment): bool
    {
        return $this->belongsToSameOrganization($user, $assignment)
            && $user->hasPermissionTo('conversations.assign');
    }

    public function delete(User $user, Model $assignment): bool
    {
        return false;
    }
}
