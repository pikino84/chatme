<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->organization_id === $organization->id;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->organization_id === $organization->id
            && $user->hasPermissionTo('organization.update');
    }

    public function manageBilling(User $user, Organization $organization): bool
    {
        return $user->organization_id === $organization->id
            && $user->hasPermissionTo('organization.manage-billing');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return false;
    }
}
