<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ChannelPolicy extends TenantPolicy
{
    public function view(User $user, Model $channel): bool
    {
        return $this->belongsToSameOrganization($user, $channel)
            && $user->hasPermissionTo('channels.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('channels.manage');
    }

    public function update(User $user, Model $channel): bool
    {
        return $this->belongsToSameOrganization($user, $channel)
            && $user->hasPermissionTo('channels.manage');
    }

    public function delete(User $user, Model $channel): bool
    {
        return $this->belongsToSameOrganization($user, $channel)
            && $user->hasPermissionTo('channels.manage');
    }
}
