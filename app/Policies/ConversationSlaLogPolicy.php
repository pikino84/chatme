<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ConversationSlaLogPolicy extends TenantPolicy
{
    public function view(User $user, Model $slaLog): bool
    {
        return $this->belongsToSameOrganization($user, $slaLog)
            && $user->hasPermissionTo('sla.view');
    }

    public function manage(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('sla.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $slaLog): bool
    {
        return false;
    }

    public function delete(User $user, Model $slaLog): bool
    {
        return false;
    }
}
