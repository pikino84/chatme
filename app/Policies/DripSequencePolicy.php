<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DripSequencePolicy extends TenantPolicy
{
    public function view(User $user, Model $sequence): bool
    {
        return $this->belongsToSameOrganization($user, $sequence)
            && $user->hasPermissionTo('drip.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('drip.create');
    }

    public function update(User $user, Model $sequence): bool
    {
        return $this->belongsToSameOrganization($user, $sequence)
            && $user->hasPermissionTo('drip.update');
    }

    public function delete(User $user, Model $sequence): bool
    {
        return $this->belongsToSameOrganization($user, $sequence)
            && $user->hasPermissionTo('drip.delete');
    }
}
