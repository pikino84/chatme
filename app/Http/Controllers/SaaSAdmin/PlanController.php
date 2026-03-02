<?php

namespace App\Http\Controllers\SaaSAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanFeatureValue;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::withCount('subscriptions')
            ->orderBy('sort_order')
            ->get();

        return view('saas-admin.plans.index', compact('plans'));
    }

    public function show(Plan $plan)
    {
        $plan->load('featureValues.feature');

        return view('saas-admin.plans.show', compact('plan'));
    }

    public function create()
    {
        $features = PlanFeature::orderBy('code')->get();

        return view('saas-admin.plans.form', [
            'plan' => null,
            'features' => $features,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans,slug',
            'description' => 'nullable|string|max:1000',
            'price_monthly' => 'required|integer|min:0',
            'price_yearly' => 'required|integer|min:0',
            'sort_order' => 'required|integer|min:0',
            'trial_days' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
        ]);

        $plan = Plan::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'price_monthly' => $validated['price_monthly'],
            'price_yearly' => $validated['price_yearly'],
            'sort_order' => $validated['sort_order'],
            'trial_days' => $validated['trial_days'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->syncFeatureValues($plan, $request->input('features', []));

        return redirect()->route('saas-admin.plans.show', $plan)
            ->with('success', "Plan '{$plan->name}' created.");
    }

    public function edit(Plan $plan)
    {
        $plan->load('featureValues.feature');
        $features = PlanFeature::orderBy('code')->get();

        return view('saas-admin.plans.form', compact('plan', 'features'));
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans,slug,' . $plan->id,
            'description' => 'nullable|string|max:1000',
            'price_monthly' => 'required|integer|min:0',
            'price_yearly' => 'required|integer|min:0',
            'sort_order' => 'required|integer|min:0',
            'trial_days' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
        ]);

        $plan->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'price_monthly' => $validated['price_monthly'],
            'price_yearly' => $validated['price_yearly'],
            'sort_order' => $validated['sort_order'],
            'trial_days' => $validated['trial_days'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->syncFeatureValues($plan, $request->input('features', []));

        return redirect()->route('saas-admin.plans.show', $plan)
            ->with('success', "Plan '{$plan->name}' updated.");
    }

    public function destroy(Plan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'Cannot delete a plan with active subscriptions.');
        }

        $plan->featureValues()->delete();
        $plan->delete();

        return redirect()->route('saas-admin.plans.index')
            ->with('success', 'Plan deleted.');
    }

    private function syncFeatureValues(Plan $plan, array $featureInputs): void
    {
        foreach ($featureInputs as $featureCode => $value) {
            $feature = PlanFeature::where('code', $featureCode)->first();
            if (! $feature) {
                continue;
            }

            if ($value === null || $value === '') {
                PlanFeatureValue::where('plan_id', $plan->id)
                    ->where('plan_feature_id', $feature->id)
                    ->delete();
                continue;
            }

            PlanFeatureValue::updateOrCreate(
                ['plan_id' => $plan->id, 'plan_feature_id' => $feature->id],
                ['value' => $value]
            );
        }
    }
}
