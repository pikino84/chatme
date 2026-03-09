<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BrandPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('brands.view');
    }

    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model) && $user->hasPermissionTo('brands.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('brands.create');
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $user->hasPermissionTo('brands.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $user->hasPermissionTo('brands.delete');
    }
}
