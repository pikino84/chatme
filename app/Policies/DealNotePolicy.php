<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DealNotePolicy extends TenantPolicy
{
    public function view(User $user, Model $note): bool
    {
        return $this->belongsToSameOrganization($user, $note);
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('deals.view');
    }

    public function delete(User $user, Model $note): bool
    {
        if (!$this->belongsToSameOrganization($user, $note)) {
            return false;
        }

        if ($user->hasPermissionTo('deals.view-all')) {
            return true;
        }

        return $note->user_id === $user->id;
    }
}
