<?php

namespace App\Http\Controllers\SaaSAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $organizations = $query->latest()->paginate(20)->withQueryString();

        return view('saas-admin.organizations.index', compact('organizations'));
    }

    public function show(Organization $organization)
    {
        $organization->loadCount('users', 'branches');

        $subscription = OrganizationSubscription::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->with('plan')
            ->latest()
            ->first();

        $users = $organization->users()->with('roles')->get();

        return view('saas-admin.organizations.show', compact('organization', 'subscription', 'users'));
    }

    public function edit(Organization $organization)
    {
        return view('saas-admin.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:organizations,slug,' . $organization->id,
        ]);

        $organization->update($validated);

        return redirect()->route('saas-admin.organizations.show', $organization)
            ->with('success', "Organization '{$organization->name}' updated.");
    }

    public function suspend(Organization $organization)
    {
        if ($organization->status === 'suspended') {
            return back()->with('error', 'Organization is already suspended.');
        }

        $organization->update(['status' => 'suspended']);

        return back()->with('success', "Organization '{$organization->name}' has been suspended.");
    }

    public function activate(Organization $organization)
    {
        if ($organization->status === 'active') {
            return back()->with('error', 'Organization is already active.');
        }

        $organization->update(['status' => 'active']);

        return back()->with('success', "Organization '{$organization->name}' has been activated.");
    }
}
