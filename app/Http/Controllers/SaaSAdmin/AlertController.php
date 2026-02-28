<?php

namespace App\Http\Controllers\SaaSAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SaasAlert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $query = SaasAlert::with(['organization', 'creator']);

        if ($request->input('active_only')) {
            $query->where('is_active', true)->whereNull('resolved_at');
        }

        $alerts = $query->latest()->paginate(20)->withQueryString();

        return view('saas-admin.alerts.index', compact('alerts'));
    }

    public function create()
    {
        $organizations = Organization::orderBy('name')->get();

        return view('saas-admin.alerts.create', compact('organizations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'nullable|exists:organizations,id',
            'type' => 'required|in:info,warning,critical,maintenance',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $validated['created_by'] = auth()->id();

        SaasAlert::create($validated);

        return redirect()->route('saas-admin.alerts.index')
            ->with('success', 'Alert created.');
    }

    public function edit(SaasAlert $alert)
    {
        $organizations = Organization::orderBy('name')->get();

        return view('saas-admin.alerts.edit', compact('alert', 'organizations'));
    }

    public function update(Request $request, SaasAlert $alert)
    {
        $validated = $request->validate([
            'organization_id' => 'nullable|exists:organizations,id',
            'type' => 'required|in:info,warning,critical,maintenance',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $alert->update($validated);

        return redirect()->route('saas-admin.alerts.index')
            ->with('success', 'Alert updated.');
    }

    public function resolve(SaasAlert $alert)
    {
        $alert->update([
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'is_active' => false,
        ]);

        return back()->with('success', "Alert '{$alert->title}' resolved.");
    }

    public function destroy(SaasAlert $alert)
    {
        $alert->delete();

        return redirect()->route('saas-admin.alerts.index')
            ->with('success', 'Alert deleted.');
    }
}
