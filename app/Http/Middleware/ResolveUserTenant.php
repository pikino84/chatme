<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveUserTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->organization_id) {
            abort(403, 'No organization associated with this account.');
        }

        $organization = $user->organization;

        if (! $organization) {
            abort(403, 'Organization not found.');
        }

        if ($organization->isSuspended()) {
            abort(403, 'Organization suspended.');
        }

        app()->instance('tenant', $organization);

        return $next($request);
    }
}
