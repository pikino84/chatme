<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request)
    {
        if (! $request->user()->can('settings.view')) {
            abort(403);
        }

        $organization = app('tenant');

        return view('settings.organization', compact('organization'));
    }

    public function update(Request $request)
    {
        if (! $request->user()->can('settings.update')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'timezone' => 'nullable|string|timezone:all',
            'logo' => 'nullable|image|max:1024',
        ]);

        $organization = app('tenant');

        $organization->name = $request->input('name');

        $settings = $organization->settings ?? [];

        if ($request->input('timezone')) {
            $settings['timezone'] = $request->input('timezone');
        }

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $path = $file->storeAs(
                "org-logos/{$organization->id}",
                Str::uuid() . '.' . $file->getClientOriginalExtension(),
                'public'
            );
            $settings['logo'] = $path;
        }

        $organization->settings = $settings;
        $organization->save();

        return back()->with('success', 'Settings updated.');
    }
}
