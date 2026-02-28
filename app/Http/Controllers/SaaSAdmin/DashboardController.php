<?php

namespace App\Http\Controllers\SaaSAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\SaasAlert;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_organizations' => Organization::count(),
            'active_organizations' => Organization::where('status', 'active')->count(),
            'suspended_organizations' => Organization::where('status', 'suspended')->count(),
            'active_subscriptions' => OrganizationSubscription::withoutGlobalScopes()
                ->whereIn('status', ['active', 'trialing'])
                ->count(),
            'canceled_subscriptions' => OrganizationSubscription::withoutGlobalScopes()
                ->where('status', 'canceled')
                ->count(),
            'monthly_revenue' => OrganizationSubscription::withoutGlobalScopes()
                ->where('status', 'active')
                ->join('plans', 'organization_subscriptions.plan_id', '=', 'plans.id')
                ->selectRaw('COALESCE(SUM(CASE WHEN billing_cycle = \'monthly\' THEN plans.price_monthly WHEN billing_cycle = \'yearly\' THEN plans.price_yearly / 12 ELSE 0 END), 0) as total')
                ->value('total'),
            'active_alerts' => SaasAlert::where('is_active', true)
                ->whereNull('resolved_at')
                ->count(),
        ];

        $recentOrgs = Organization::latest()->take(5)->get();

        return view('saas-admin.dashboard', compact('stats', 'recentOrgs'));
    }
}
