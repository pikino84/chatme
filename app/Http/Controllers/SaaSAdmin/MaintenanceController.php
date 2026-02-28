<?php

namespace App\Http\Controllers\SaaSAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;

class MaintenanceController extends Controller
{
    public function index()
    {
        $organizations = Organization::orderBy('name')->get()->map(function ($org) {
            $org->in_maintenance = $org->settings['maintenance_mode'] ?? false;
            return $org;
        });

        return view('saas-admin.maintenance.index', compact('organizations'));
    }

    public function toggle(Organization $organization)
    {
        $settings = $organization->settings ?? [];
        $currentMode = $settings['maintenance_mode'] ?? false;
        $settings['maintenance_mode'] = !$currentMode;
        $settings['maintenance_toggled_at'] = now()->toIso8601String();

        $organization->update(['settings' => $settings]);

        $action = $settings['maintenance_mode'] ? 'enabled' : 'disabled';

        return back()->with('success', "Maintenance mode {$action} for '{$organization->name}'.");
    }
}
