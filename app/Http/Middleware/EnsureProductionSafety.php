<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureProductionSafety
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && config('app.debug') === true) {
            Log::critical('APP_DEBUG is enabled in production! This is a security risk.');
        }

        return $next($request);
    }
}
