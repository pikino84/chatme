<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveSaaSAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasRole('saas_admin')) {
            abort(403, 'Access denied. SaaS Admin role required.');
        }

        if ($user->organization_id !== null) {
            abort(403, 'Access denied. SaaS Admin must not belong to any organization.');
        }

        return $next($request);
    }
}
