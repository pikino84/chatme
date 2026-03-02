<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelForm;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        if ($request->user()->cannot('channels.view')) {
            abort(403);
        }

        $channels = Channel::withCount('conversations')
            ->orderBy('name')
            ->get();

        return view('settings.channels.index', compact('channels'));
    }

    public function create(Request $request)
    {
        if ($request->user()->cannot('channels.manage')) {
            abort(403);
        }

        return view('settings.channels.form', ['channel' => null]);
    }

    public function store(Request $request)
    {
        if ($request->user()->cannot('channels.manage')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:whatsapp,webchat,facebook,instagram',
            'phone_number_id' => 'required_if:type,whatsapp|nullable|string|max:255',
            'waba_id' => 'required_if:type,whatsapp|nullable|string|max:255',
            'access_token' => 'required_if:type,whatsapp|nullable|string|max:1000',
            'verify_token' => 'nullable|string|max:255',
            'app_secret' => 'nullable|string|max:255',
            'display_phone' => 'required_if:type,whatsapp|nullable|string|max:50',
            'allowed_origins' => 'nullable|string',
            'page_id' => 'required_if:type,facebook|required_if:type,instagram|nullable|string|max:255',
            'page_access_token' => 'required_if:type,facebook|required_if:type,instagram|nullable|string|max:1000',
            'instagram_account_id' => 'required_if:type,instagram|nullable|string|max:255',
        ]);

        $config = $this->buildConfig($request);

        $channel = Channel::create([
            'organization_id' => app('tenant')->id,
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'configuration' => $config,
            'is_active' => true,
        ]);

        return redirect()->route('settings.channels.show', $channel)
            ->with('success', 'Canal creado exitosamente.');
    }

    public function show(Request $request, Channel $channel)
    {
        if ($request->user()->cannot('channels.view')) {
            abort(403);
        }

        if ($channel->organization_id !== $request->user()->organization_id) {
            abort(404);
        }

        $channel->loadCount('conversations');
        $channel->load('form');

        $webhookUrl = null;
        $widgetSnippet = null;
        $formTemplates = [];

        if ($channel->type === 'whatsapp') {
            $webhookUrl = url("/api/webhooks/whatsapp/{$channel->uuid}");
        } elseif ($channel->type === 'webchat') {
            $widgetSnippet = $this->generateWidgetSnippet($channel);
            $formTemplates = config('form_templates', []);
        } elseif ($channel->type === 'facebook') {
            $webhookUrl = url("/api/webhooks/facebook/{$channel->uuid}");
        } elseif ($channel->type === 'instagram') {
            $webhookUrl = url("/api/webhooks/instagram/{$channel->uuid}");
        }

        return view('settings.channels.show', compact(
            'channel', 'webhookUrl', 'widgetSnippet', 'formTemplates'
        ));
    }

    public function edit(Request $request, Channel $channel)
    {
        if ($request->user()->cannot('channels.manage')) {
            abort(403);
        }

        if ($channel->organization_id !== $request->user()->organization_id) {
            abort(404);
        }

        return view('settings.channels.form', compact('channel'));
    }

    public function update(Request $request, Channel $channel)
    {
        if ($request->user()->cannot('channels.manage')) {
            abort(403);
        }

        if ($channel->organization_id !== $request->user()->organization_id) {
            abort(404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone_number_id' => 'nullable|string|max:255',
            'waba_id' => 'nullable|string|max:255',
            'access_token' => 'nullable|string|max:1000',
            'verify_token' => 'nullable|string|max:255',
            'app_secret' => 'nullable|string|max:255',
            'display_phone' => 'nullable|string|max:50',
            'allowed_origins' => 'nullable|string',
            'template_key' => 'nullable|string',
            'page_id' => 'nullable|string|max:255',
            'page_access_token' => 'nullable|string|max:1000',
            'instagram_account_id' => 'nullable|string|max:255',
        ]);

        $config = $this->buildConfig($request, $channel);
        $channel->update([
            'name' => $request->input('name'),
            'configuration' => $config,
        ]);

        // Handle form template for webchat
        if ($channel->type === 'webchat') {
            $this->syncFormTemplate($channel, $request->input('template_key'));
        }

        return redirect()->route('settings.channels.show', $channel)
            ->with('success', 'Canal actualizado exitosamente.');
    }

    public function toggleActive(Request $request, Channel $channel)
    {
        if ($request->user()->cannot('channels.manage')) {
            abort(403);
        }

        if ($channel->organization_id !== $request->user()->organization_id) {
            abort(404);
        }

        $channel->update(['is_active' => !$channel->is_active]);

        return back()->with('success', $channel->is_active ? 'Canal activado.' : 'Canal desactivado.');
    }

    public function destroy(Request $request, Channel $channel)
    {
        if ($request->user()->cannot('channels.manage')) {
            abort(403);
        }

        if ($channel->organization_id !== $request->user()->organization_id) {
            abort(404);
        }

        if ($channel->conversations()->count() > 0) {
            return back()->with('error', 'No se puede eliminar un canal que tiene conversaciones.');
        }

        $channel->delete();

        return redirect()->route('settings.channels')
            ->with('success', 'Canal eliminado.');
    }

    private function buildConfig(Request $request, ?Channel $existing = null): array
    {
        $type = $existing ? $existing->type : $request->input('type');

        if ($type === 'whatsapp') {
            $config = [
                'phone_number_id' => $request->input('phone_number_id'),
                'waba_id' => $request->input('waba_id'),
                'verify_token' => $request->input('verify_token'),
                'display_phone' => $request->input('display_phone'),
            ];

            // For updates, keep existing secrets if not provided
            if ($existing) {
                $existingConfig = $existing->configuration ?? [];
                $config['access_token'] = $request->filled('access_token')
                    ? $request->input('access_token')
                    : ($existingConfig['access_token'] ?? '');
                $config['app_secret'] = $request->filled('app_secret')
                    ? $request->input('app_secret')
                    : ($existingConfig['app_secret'] ?? '');
            } else {
                $config['access_token'] = $request->input('access_token');
                $config['app_secret'] = $request->input('app_secret');
            }

            return $config;
        }

        if ($type === 'facebook') {
            $config = [
                'page_id' => $request->input('page_id'),
                'verify_token' => $request->input('verify_token'),
            ];

            if ($existing) {
                $existingConfig = $existing->configuration ?? [];
                $config['page_access_token'] = $request->filled('page_access_token')
                    ? $request->input('page_access_token')
                    : ($existingConfig['page_access_token'] ?? '');
                $config['app_secret'] = $request->filled('app_secret')
                    ? $request->input('app_secret')
                    : ($existingConfig['app_secret'] ?? '');
            } else {
                $config['page_access_token'] = $request->input('page_access_token');
                $config['app_secret'] = $request->input('app_secret');
            }

            return $config;
        }

        if ($type === 'instagram') {
            $config = [
                'instagram_account_id' => $request->input('instagram_account_id'),
                'page_id' => $request->input('page_id'),
                'verify_token' => $request->input('verify_token'),
            ];

            if ($existing) {
                $existingConfig = $existing->configuration ?? [];
                $config['page_access_token'] = $request->filled('page_access_token')
                    ? $request->input('page_access_token')
                    : ($existingConfig['page_access_token'] ?? '');
                $config['app_secret'] = $request->filled('app_secret')
                    ? $request->input('app_secret')
                    : ($existingConfig['app_secret'] ?? '');
            } else {
                $config['page_access_token'] = $request->input('page_access_token');
                $config['app_secret'] = $request->input('app_secret');
            }

            return $config;
        }

        // Webchat
        $origins = $request->input('allowed_origins', '');
        $originsArray = array_filter(array_map('trim', explode("\n", $origins)));

        return [
            'allowed_origins' => array_values($originsArray),
        ];
    }

    private function generateWidgetSnippet(Channel $channel): string
    {
        $apiUrl = url('/api/webchat/' . $channel->uuid);
        return '<script src="' . url('/webchat/widget.js') . '" data-channel="' . $channel->uuid . '" data-api="' . $apiUrl . '"></script>';
    }

    private function syncFormTemplate(Channel $channel, ?string $templateKey): void
    {
        if (empty($templateKey)) {
            // Remove form if template key is cleared
            if ($channel->form) {
                $channel->form->delete();
            }
            return;
        }

        $templates = config('form_templates', []);
        if (!isset($templates[$templateKey])) {
            return;
        }

        $template = $templates[$templateKey];

        ChannelForm::updateOrCreate(
            ['channel_id' => $channel->id],
            [
                'template_key' => $templateKey,
                'schema' => ['fields' => $template['fields']],
                'is_active' => true,
            ]
        );
    }
}
