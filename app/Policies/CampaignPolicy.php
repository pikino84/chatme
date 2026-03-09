<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CampaignPolicy extends TenantPolicy
{
    public function view(User $user, Model $campaign): bool
    {
        return $this->belongsToSameOrganization($user, $campaign)
            && $user->hasPermissionTo('campaigns.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('campaigns.create');
    }

    public function update(User $user, Model $campaign): bool
    {
        return $this->belongsToSameOrganization($user, $campaign)
            && $user->hasPermissionTo('campaigns.create')
            && $campaign->canBeEdited();
    }

    public function send(User $user, Model $campaign): bool
    {
        return $this->belongsToSameOrganization($user, $campaign)
            && $user->hasPermissionTo('campaigns.send');
    }

    public function delete(User $user, Model $campaign): bool
    {
        return $this->belongsToSameOrganization($user, $campaign)
            && $user->hasPermissionTo('campaigns.delete')
            && $campaign->isDraft();
    }
}
