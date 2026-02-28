<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class TenantPolicy
{
    protected function belongsToSameOrganization(User $user, Model $model): bool
    {
        return $user->organization_id === $model->organization_id;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->belongsToSameOrganization($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->belongsToSameOrganization($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->belongsToSameOrganization($user, $model);
    }
}
