<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplatePolicy extends TenantPolicy
{
    public function view(User $user, Model $template): bool
    {
        return $this->belongsToSameOrganization($user, $template)
            && $user->hasPermissionTo('whatsapp_templates.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermissionTo('whatsapp_templates.create');
    }

    public function update(User $user, Model $template): bool
    {
        return $this->belongsToSameOrganization($user, $template)
            && $user->hasPermissionTo('whatsapp_templates.create')
            && $template->isRejected();
    }

    public function delete(User $user, Model $template): bool
    {
        return $this->belongsToSameOrganization($user, $template)
            && $user->hasPermissionTo('whatsapp_templates.delete');
    }
}
