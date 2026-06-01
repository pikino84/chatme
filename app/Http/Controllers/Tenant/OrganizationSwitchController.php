<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class OrganizationSwitchController extends Controller
{
    /**
     * Cambia el "negocio activo" del login. Como casi todo el código asume
     * que users.organization_id es el tenant activo, el cambio se persiste
     * mutando ese campo (no se usa una org-en-sesión que obligaría a refactor).
     */
    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        $user = $request->user();

        // Solo puede cambiar a un negocio al que está vinculado...
        abort_unless($user->canAccessOrganization($organization->id), 403);

        // ...y que esté activo (los 'pending' aún no son utilizables).
        if (! $organization->isActive()) {
            return back()->with('error', 'Ese negocio aún no está activo.');
        }

        if ($user->organization_id !== $organization->id) {
            $user->update(['organization_id' => $organization->id]);
        }

        return redirect()->route('dashboard')->with('success', "Estás en: {$organization->name}.");
    }
}
