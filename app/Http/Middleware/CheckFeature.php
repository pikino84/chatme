<?php

namespace App\Http\Middleware;

use App\Services\BillingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    public function __construct(private readonly BillingService $billingService) {}

    public function handle(Request $request, Closure $next, string $featureCode): Response
    {
        if (!app()->bound('tenant')) {
            return $next($request);
        }

        $organization = app('tenant');

        if (!$this->billingService->checkFeature($organization, $featureCode)) {
            abort(403, "Feature '{$featureCode}' is not available on your current plan.");
        }

        return $next($request);
    }
}
