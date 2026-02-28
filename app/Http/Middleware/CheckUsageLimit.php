<?php

namespace App\Http\Middleware;

use App\Services\BillingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUsageLimit
{
    public function __construct(private readonly BillingService $billingService) {}

    public function handle(Request $request, Closure $next, string $featureCode): Response
    {
        if (!app()->bound('tenant')) {
            return $next($request);
        }

        $organization = app('tenant');

        if (!$this->billingService->checkLimit($organization, $featureCode)) {
            abort(429, "Usage limit reached for '{$featureCode}'. Please upgrade your plan.");
        }

        return $next($request);
    }
}
