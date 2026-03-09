<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Models\Channel;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        if (! $request->user()->can('automations.view')) {
            abort(403);
        }

        $rules = AutomationRule::where('organization_id', app('tenant')->id)
            ->with('channel')
            ->orderBy('type')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('automations.index', compact('rules'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', AutomationRule::class);

        $channels = Channel::where('organization_id', app('tenant')->id)
            ->where('is_active', true)
            ->get();

        return view('automations.form', ['rule' => null, 'channels' => $channels]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', AutomationRule::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:auto_assign,auto_response,stale_alert',
            'channel_id' => 'nullable|exists:channels,id',
            'is_active' => 'boolean',
            // Type-specific config
            'config.method' => 'required_if:type,auto_assign|nullable|string|in:round_robin,least_busy',
            'config.message' => 'required_if:type,auto_response|nullable|string|max:5000',
            'config.threshold_minutes' => 'required_if:type,stale_alert|nullable|integer|min:5',
        ]);

        AutomationRule::create([
            'organization_id' => app('tenant')->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'channel_id' => $data['channel_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'config' => $data['config'] ?? [],
        ]);

        return redirect()->route('automations.index')->with('success', 'Regla de automatización creada.');
    }

    public function edit(Request $request, AutomationRule $rule)
    {
        $this->authorize('update', $rule);

        $channels = Channel::where('organization_id', app('tenant')->id)
            ->where('is_active', true)
            ->get();

        return view('automations.form', compact('rule', 'channels'));
    }

    public function update(Request $request, AutomationRule $rule)
    {
        $this->authorize('update', $rule);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'channel_id' => 'nullable|exists:channels,id',
            'is_active' => 'boolean',
            'config.method' => 'nullable|string|in:round_robin,least_busy',
            'config.message' => 'nullable|string|max:5000',
            'config.threshold_minutes' => 'nullable|integer|min:5',
        ]);

        $rule->update([
            'name' => $data['name'],
            'channel_id' => $data['channel_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'config' => $data['config'] ?? [],
        ]);

        return redirect()->route('automations.index')->with('success', 'Regla actualizada.');
    }

    public function toggleActive(Request $request, AutomationRule $rule)
    {
        $this->authorize('update', $rule);

        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('success', $rule->is_active ? 'Regla activada.' : 'Regla desactivada.');
    }

    public function destroy(Request $request, AutomationRule $rule)
    {
        $this->authorize('delete', $rule);

        $rule->delete();

        return redirect()->route('automations.index')->with('success', 'Regla eliminada.');
    }
}
