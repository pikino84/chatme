<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ContactPolicy extends TenantPolicy
{
    public function view(User $user, Model $contact): bool
    {
        return $this->belongsToSameOrganization($user, $contact)
            && $user->hasPermissionTo('contacts.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('contacts.create');
    }

    public function update(User $user, Model $contact): bool
    {
        return $this->belongsToSameOrganization($user, $contact)
            && $user->hasPermissionTo('contacts.update');
    }

    public function delete(User $user, Model $contact): bool
    {
        return $this->belongsToSameOrganization($user, $contact)
            && $user->hasPermissionTo('contacts.delete');
    }

    public function import(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('contacts.import');
    }
}
