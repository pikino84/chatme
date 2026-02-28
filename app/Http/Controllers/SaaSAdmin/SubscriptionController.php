<?php

namespace App\Http\Controllers\SaaSAdmin;

use App\Http\Controllers\Controller;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = OrganizationSubscription::withoutGlobalScopes()
            ->with(['organization', 'plan']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $subscriptions = $query->latest()->paginate(20)->withQueryString();

        return view('saas-admin.subscriptions.index', compact('subscriptions'));
    }

    public function show(int $subscriptionId)
    {
        $subscription = OrganizationSubscription::withoutGlobalScopes()
            ->with(['organization', 'plan'])
            ->findOrFail($subscriptionId);

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return view('saas-admin.subscriptions.show', compact('subscription', 'plans'));
    }

    public function update(Request $request, int $subscriptionId)
    {
        $subscription = OrganizationSubscription::withoutGlobalScopes()
            ->findOrFail($subscriptionId);

        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'status' => 'required|in:active,trialing,past_due,canceled',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $subscription->update($validated);

        return redirect()->route('saas-admin.subscriptions.show', $subscription->id)
            ->with('success', 'Subscription updated.');
    }
}
