<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BranchPolicy extends TenantPolicy
{
    public function view(User $user, Model $model): bool
    {
        return parent::belongsToSameOrganization($user, $model)
            && $user->hasPermissionTo('branches.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('branches.create');
    }

    public function update(User $user, Model $model): bool
    {
        return parent::belongsToSameOrganization($user, $model)
            && $user->hasPermissionTo('branches.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::belongsToSameOrganization($user, $model)
            && $user->hasPermissionTo('branches.delete');
    }
}
