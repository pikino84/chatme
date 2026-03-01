<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class KbCategoryPolicy extends TenantPolicy
{
    public function view(User $user, Model $category): bool
    {
        return $this->belongsToSameOrganization($user, $category)
            && $user->hasPermissionTo('kb.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('kb.create');
    }

    public function update(User $user, Model $category): bool
    {
        return $this->belongsToSameOrganization($user, $category)
            && $user->hasPermissionTo('kb.update');
    }

    public function delete(User $user, Model $category): bool
    {
        if (!$this->belongsToSameOrganization($user, $category)) {
            return false;
        }

        if (!$user->hasPermissionTo('kb.delete')) {
            return false;
        }

        return $category->articles()->count() === 0;
    }
}
