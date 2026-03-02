<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\BillingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private BillingService $billingService) {}

    public function index(Request $request)
    {
        if (! $request->user()->can('organization.manage-billing')) {
            abort(403);
        }

        $org = app('tenant');
        $subscription = $this->billingService->getActiveSubscription($org);
        $plan = $subscription?->plan?->load('featureValues.feature');

        $features = collect();
        $limits = collect();

        if ($plan) {
            foreach ($plan->featureValues as $fv) {
                if ($fv->feature->isBoolean()) {
                    $features->push([
                        'code' => $fv->feature->code,
                        'description' => $fv->feature->description,
                        'enabled' => $fv->value === 'true',
                    ]);
                } elseif ($fv->feature->isLimit()) {
                    $isUnlimited = $fv->isUnlimited();
                    $limit = $isUnlimited ? null : (int) $fv->value;
                    $usage = $this->billingService->getUsage($org, $fv->feature->code);
                    $percentage = ($isUnlimited || ! $limit) ? 0 : min(100, round(($usage / $limit) * 100));

                    $limits->push([
                        'code' => $fv->feature->code,
                        'description' => $fv->feature->description,
                        'limit' => $limit,
                        'usage' => $usage,
                        'percentage' => $percentage,
                        'isUnlimited' => $isUnlimited,
                    ]);
                }
            }
        }

        $atLimit = $limits->filter(fn ($l) => ! $l['isUnlimited'] && $l['usage'] >= $l['limit']);

        return view('billing.subscription', compact('subscription', 'plan', 'features', 'limits', 'atLimit'));
    }

    public function plans(Request $request)
    {
        if (! $request->user()->can('organization.manage-billing')) {
            abort(403);
        }

        $org = app('tenant');
        $plans = Plan::where('is_active', true)
            ->with('featureValues.feature')
            ->orderBy('sort_order')
            ->get();

        $subscription = $this->billingService->getActiveSubscription($org);
        $currentPlan = $subscription?->plan;

        return view('billing.plans', compact('plans', 'currentPlan'));
    }

    public function changePlan(Request $request)
    {
        if (! $request->user()->can('organization.manage-billing')) {
            abort(403);
        }

        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $org = app('tenant');
        $plan = Plan::findOrFail($request->input('plan_id'));

        $this->billingService->changePlan($org, $plan);

        return redirect()->route('billing.index')->with('success', "Plan changed to {$plan->name}.");
    }
}
