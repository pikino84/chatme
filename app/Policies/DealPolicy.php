<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DealPolicy extends TenantPolicy
{
    public function view(User $user, Model $deal): bool
    {
        if (!$this->belongsToSameOrganization($user, $deal)) {
            return false;
        }

        if ($user->hasPermissionTo('deals.view-all')) {
            return true;
        }

        return $user->hasPermissionTo('deals.view')
            && $deal->assigned_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('deals.create');
    }

    public function update(User $user, Model $deal): bool
    {
        if (!$this->belongsToSameOrganization($user, $deal)) {
            return false;
        }

        if ($user->hasPermissionTo('deals.view-all')) {
            return true;
        }

        return $user->hasPermissionTo('deals.update')
            && $deal->assigned_user_id === $user->id;
    }

    public function delete(User $user, Model $deal): bool
    {
        return $this->belongsToSameOrganization($user, $deal)
            && $user->hasPermissionTo('deals.delete');
    }

    public function assign(User $user, Model $deal): bool
    {
        return $this->belongsToSameOrganization($user, $deal)
            && $user->hasPermissionTo('deals.assign');
    }

    public function manageCommissions(User $user, Model $deal): bool
    {
        return $this->belongsToSameOrganization($user, $deal)
            && $user->hasPermissionTo('deals.manage-commissions');
    }
}
