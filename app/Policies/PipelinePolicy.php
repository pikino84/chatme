<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PipelinePolicy extends TenantPolicy
{
    public function view(User $user, Model $pipeline): bool
    {
        return $this->belongsToSameOrganization($user, $pipeline)
            && $user->hasPermissionTo('pipelines.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('pipelines.create');
    }

    public function update(User $user, Model $pipeline): bool
    {
        return $this->belongsToSameOrganization($user, $pipeline)
            && $user->hasPermissionTo('pipelines.update');
    }

    public function delete(User $user, Model $pipeline): bool
    {
        if (!$this->belongsToSameOrganization($user, $pipeline)) {
            return false;
        }

        if (!$user->hasPermissionTo('pipelines.delete')) {
            return false;
        }

        return $pipeline->deals()->count() === 0;
    }
}
