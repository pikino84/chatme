<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AutomationRulePolicy extends TenantPolicy
{
    public function view(User $user, Model $rule): bool
    {
        return $this->belongsToSameOrganization($user, $rule)
            && $user->hasPermissionTo('automations.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('automations.create');
    }

    public function update(User $user, Model $rule): bool
    {
        return $this->belongsToSameOrganization($user, $rule)
            && $user->hasPermissionTo('automations.update');
    }

    public function delete(User $user, Model $rule): bool
    {
        return $this->belongsToSameOrganization($user, $rule)
            && $user->hasPermissionTo('automations.delete');
    }
}
