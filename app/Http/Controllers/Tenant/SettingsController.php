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
            'business_hours.enabled' => 'nullable',
            'business_hours.schedule.*.enabled' => 'nullable',
            'business_hours.schedule.*.start' => 'nullable|date_format:H:i',
            'business_hours.schedule.*.end' => 'nullable|date_format:H:i',
            'auto_responses.welcome.enabled' => 'nullable',
            'auto_responses.welcome.message' => 'nullable|string|max:1000',
            'auto_responses.out_of_hours.enabled' => 'nullable',
            'auto_responses.out_of_hours.message' => 'nullable|string|max:1000',
            'auto_responses.agent_timeout.enabled' => 'nullable',
            'auto_responses.agent_timeout.message' => 'nullable|string|max:1000',
            'auto_responses.agent_timeout.timeout_minutes' => 'nullable|integer|min:1|max:120',
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

        // Business hours
        if ($request->has('business_hours')) {
            $bh = $request->input('business_hours');
            $settings['business_hours'] = [
                'enabled' => !empty($bh['enabled']),
                'schedule' => [],
            ];
            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
                $dayData = $bh['schedule'][$day] ?? [];
                $settings['business_hours']['schedule'][$day] = [
                    'enabled' => !empty($dayData['enabled']),
                    'start' => $dayData['start'] ?? '09:00',
                    'end' => $dayData['end'] ?? '18:00',
                ];
            }
        }

        // Auto-responses
        if ($request->has('auto_responses')) {
            $ar = $request->input('auto_responses');
            $settings['auto_responses'] = [
                'welcome' => [
                    'enabled' => !empty($ar['welcome']['enabled']),
                    'message' => $ar['welcome']['message'] ?? '',
                ],
                'out_of_hours' => [
                    'enabled' => !empty($ar['out_of_hours']['enabled']),
                    'message' => $ar['out_of_hours']['message'] ?? '',
                ],
                'agent_timeout' => [
                    'enabled' => !empty($ar['agent_timeout']['enabled']),
                    'timeout_minutes' => (int) ($ar['agent_timeout']['timeout_minutes'] ?? 10),
                    'message' => $ar['agent_timeout']['message'] ?? '',
                ],
            ];
        }

        $organization->settings = $settings;
        $organization->save();

        return back()->with('success', 'Configuración guardada.');
    }
}
