<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class KbArticlePolicy extends TenantPolicy
{
    public function view(User $user, Model $article): bool
    {
        return $this->belongsToSameOrganization($user, $article)
            && $user->hasPermissionTo('kb.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('kb.create');
    }

    public function update(User $user, Model $article): bool
    {
        return $this->belongsToSameOrganization($user, $article)
            && $user->hasPermissionTo('kb.update');
    }

    public function delete(User $user, Model $article): bool
    {
        return $this->belongsToSameOrganization($user, $article)
            && $user->hasPermissionTo('kb.delete');
    }

    public function publish(User $user, Model $article): bool
    {
        return $this->belongsToSameOrganization($user, $article)
            && $user->hasPermissionTo('kb.publish');
    }
}
