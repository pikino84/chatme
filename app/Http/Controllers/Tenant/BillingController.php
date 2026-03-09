<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\BillingService;
use App\Services\StripeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private BillingService $billingService,
        private StripeService $stripeService,
    ) {
    }

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

        $stripeConfigured = $this->stripeService->isConfigured();

        return view('billing.subscription', compact('subscription', 'plan', 'features', 'limits', 'atLimit', 'stripeConfigured'));
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

        $stripeConfigured = $this->stripeService->isConfigured();

        return view('billing.plans', compact('plans', 'currentPlan', 'stripeConfigured'));
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

        return redirect()->route('billing.index')->with('success', "Plan cambiado a {$plan->name}.");
    }

    public function checkout(Request $request)
    {
        if (! $request->user()->can('organization.manage-billing')) {
            abort(403);
        }

        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'cycle' => 'required|in:monthly,yearly',
        ]);

        $org = app('tenant');
        $plan = Plan::findOrFail($request->input('plan_id'));

        if (! $this->stripeService->isConfigured()) {
            // Fallback: direct plan change without payment
            $this->billingService->changePlan($org, $plan);

            return redirect()->route('billing.index')->with('success', "Plan cambiado a {$plan->name}.");
        }

        $session = $this->stripeService->createCheckoutSession(
            $org,
            $plan,
            $request->input('cycle'),
            route('billing.checkout.success'),
            route('billing.index'),
        );

        if (! $session) {
            return back()->with('error', 'No se pudo crear la sesión de pago. Intente más tarde.');
        }

        return redirect()->away($session['url']);
    }

    public function checkoutSuccess(Request $request)
    {
        return redirect()->route('billing.index')->with('success', 'Pago procesado exitosamente.');
    }

    public function portal(Request $request)
    {
        if (! $request->user()->can('organization.manage-billing')) {
            abort(403);
        }

        $org = app('tenant');

        $session = $this->stripeService->createPortalSession(
            $org,
            route('billing.index'),
        );

        if (! $session) {
            return back()->with('error', 'No se pudo acceder al portal de facturación.');
        }

        return redirect()->away($session['url']);
    }

    public function cancel(Request $request)
    {
        if (! $request->user()->can('organization.manage-billing')) {
            abort(403);
        }

        $org = app('tenant');
        $subscription = $this->billingService->getActiveSubscription($org);

        if (! $subscription) {
            return back()->with('error', 'No hay suscripción activa.');
        }

        // Cancel on Stripe if applicable
        if ($subscription->stripe_subscription_id) {
            $this->stripeService->cancelSubscription($subscription->stripe_subscription_id);
        }

        $this->billingService->cancel($org);

        return redirect()->route('billing.index')->with('success', 'Suscripción cancelada. Tienes acceso hasta el fin del periodo.');
    }
}
