<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $slug = explode('.', $host)[0] ?? null;

        if (!$slug) {
            abort(404, 'Tenant not found.');
        }

        $organization = Organization::where('slug', $slug)->first();

        if (!$organization) {
            abort(404, 'Tenant not found.');
        }

        if ($organization->isSuspended()) {
            abort(403, 'Organization suspended.');
        }

        if ($organization->settings['maintenance_mode'] ?? false) {
            abort(503, 'Organization is under maintenance. Please try again later.');
        }

        app()->instance('tenant', $organization);
        $request->merge(['tenant' => $organization]);

        return $next($request);
    }
}
