<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ConversationTransferPolicy extends TenantPolicy
{
    public function view(User $user, Model $transfer): bool
    {
        return $this->belongsToSameOrganization($user, $transfer);
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('conversations.transfer');
    }

    public function update(User $user, Model $transfer): bool
    {
        return false;
    }

    public function delete(User $user, Model $transfer): bool
    {
        return false;
    }
}
