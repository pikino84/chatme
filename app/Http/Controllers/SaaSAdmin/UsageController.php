<?php

namespace App\Http\Controllers\SaaSAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationUsageMonthly;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', now()->format('Y-m'));

        $usage = OrganizationUsageMonthly::withoutGlobalScopes()
            ->where('period', $period)
            ->with('organization')
            ->orderByDesc('usage')
            ->paginate(20)
            ->withQueryString();

        $periods = OrganizationUsageMonthly::withoutGlobalScopes()
            ->select('period')
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period');

        return view('saas-admin.usage.index', compact('usage', 'periods', 'period'));
    }
}
