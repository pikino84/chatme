<?php

namespace App\Http\Middleware;

use App\Services\BillingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function __construct(private readonly BillingService $billingService) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!app()->bound('tenant')) {
            return $next($request);
        }

        $organization = app('tenant');

        if (!$this->billingService->hasAccess($organization)) {
            abort(403, 'Subscription required. Please subscribe to a plan.');
        }

        if ($this->billingService->isReadOnly($organization) && $request->isMethod('POST')) {
            abort(403, 'Subscription expired. Account is in read-only mode.');
        }

        return $next($request);
    }
}
