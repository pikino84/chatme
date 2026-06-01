<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Channel;
use App\Models\ChannelForm;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Services\BillingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChannelController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        if ($request->user()->cannot('channels.view')) {
            abort(403);
        }

        $channels = Channel::with('brand:id,name,color')
            ->withCount('conversations')
            ->orderBy('name')
            ->get();

        return view('settings.channels.index', compact('channels'));
    }

    public function create(Request $request)
    {
        if ($request->user()->cannot('channels.manage')) {
            abort(403);
        }

        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        return view('settings.channels.form', ['channel' => null, 'brands' => $brands]);
    }

    public function store(Request $request)
    {
        if ($request->user()->cannot('channels.manage')) {
            abort(403);
        }

        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:whatsapp,webchat,facebook,instagram',
            'brand_id' => 'nullable|exists:brands,id',
        ];

        switch ($request->input('type')) {
            case 'whatsapp':
                $rules['phone_number_id'] = 'required|string|max:255';
                $rules['waba_id'] = 'required|string|max:255';
                $rules['access_token'] = 'required|string|max:1000';
                $rules['verify_token'] = 'required|string|max:255';
                $rules['app_secret'] = 'required|string|max:255';
                $rules['display_phone'] = 'required|string|max:50';
                break;
            case 'webchat':
                $rules['allowed_origins'] = 'nullable|string';
                break;
            case 'facebook':
                $rules['page_id'] = 'required|string|max:255';
                $rules['page_access_token'] = 'required|string|max:1000';
                $rules['app_secret'] = 'required|string|max:255';
                $rules['verify_token'] = 'required|string|max:255';
                break;
            case 'instagram':
                $rules['instagram_account_id'] = 'required|string|max:255';
                $rules['page_id'] = 'required|string|max:255';
                $rules['page_access_token'] = 'required|string|max:1000';
                $rules['app_secret'] = 'required|string|max:255';
                $rules['verify_token'] = 'required|string|max:255';
                break;
        }

        $request->validate($rules);

        if ($request->input('type') === 'webchat' && !$this->hasLandingPipeline()) {
            return back()->withInput()->with('error', $this->pipelineRequiredMessage());
        }

        $config = $this->buildConfig($request);

        $channel = Channel::create([
            'organization_id' => app('tenant')->id,
            'brand_id' => $request->input('brand_id') ?: null,
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

        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        return view('settings.channels.form', compact('channel', 'brands'));
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

        $request->validate([
            'brand_id' => 'nullable|exists:brands,id',
        ]);

        $config = $this->buildConfig($request, $channel);
        $channel->update([
            'name' => $request->input('name'),
            'brand_id' => $request->input('brand_id') ?: null,
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

    // ─── Wizard ───────────────────────────────────────────────

    public function wizard(Request $request)
    {
        if ($request->user()->cannot('channels.manage')) {
            abort(403);
        }

        $tenant = app('tenant');
        $billing = app(BillingService::class);
        $currentCount = Channel::count();
        $canCreate = $this->canCreateMoreChannels($tenant, $billing);
        $webchatEnabled = $billing->checkFeature($tenant, 'webchat_enabled');
        $whatsappEnabled = $billing->checkFeature($tenant, 'whatsapp_enabled');
        $hasPipeline = $this->hasLandingPipeline();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        return view('settings.channels.wizard', compact(
            'currentCount', 'canCreate', 'webchatEnabled', 'whatsappEnabled', 'hasPipeline', 'brands'
        ));
    }

    public function wizardValidate(Request $request)
    {
        if ($request->user()->cannot('channels.manage')) {
            return response()->json(['success' => false, 'message' => 'Sin permisos.'], 403);
        }

        $request->validate([
            'type' => 'required|in:whatsapp,webchat,facebook,instagram',
        ]);

        $type = $request->input('type');

        try {
            if ($type === 'whatsapp') {
                $request->validate([
                    'phone_number_id' => 'required|string|max:255',
                    'access_token' => 'required|string|max:1000',
                ]);

                $response = Http::withToken($request->input('access_token'))
                    ->timeout(10)
                    ->get("https://graph.facebook.com/v21.0/{$request->input('phone_number_id')}");

                if (!$response->successful()) {
                    $error = $response->json('error.message', 'Credenciales inválidas');
                    return response()->json(['success' => false, 'message' => $error]);
                }

                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'Conexión exitosa',
                    'details' => [
                        'display_name' => $data['verified_name'] ?? $data['display_phone_number'] ?? 'Verificado',
                        'phone' => $data['display_phone_number'] ?? '',
                    ],
                ]);
            }

            if ($type === 'facebook') {
                $request->validate([
                    'page_id' => 'required|string|max:255',
                    'page_access_token' => 'required|string|max:1000',
                ]);

                $response = Http::timeout(10)
                    ->get("https://graph.facebook.com/v21.0/{$request->input('page_id')}", [
                        'fields' => 'name,id',
                        'access_token' => $request->input('page_access_token'),
                    ]);

                if (!$response->successful()) {
                    $error = $response->json('error.message', 'Credenciales inválidas');
                    return response()->json(['success' => false, 'message' => $error]);
                }

                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'Conexión exitosa',
                    'details' => ['page_name' => $data['name'] ?? 'Página verificada'],
                ]);
            }

            if ($type === 'instagram') {
                $request->validate([
                    'instagram_account_id' => 'required|string|max:255',
                    'page_access_token' => 'required|string|max:1000',
                ]);

                $response = Http::timeout(10)
                    ->get("https://graph.facebook.com/v21.0/{$request->input('instagram_account_id')}", [
                        'fields' => 'name,id,username',
                        'access_token' => $request->input('page_access_token'),
                    ]);

                if (!$response->successful()) {
                    $error = $response->json('error.message', 'Credenciales inválidas');
                    return response()->json(['success' => false, 'message' => $error]);
                }

                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'Conexión exitosa',
                    'details' => [
                        'account_name' => $data['name'] ?? $data['username'] ?? 'Cuenta verificada',
                        'username' => $data['username'] ?? '',
                    ],
                ]);
            }

            // Webchat — requiere un embudo donde aterricen los contactos
            if (!$this->hasLandingPipeline()) {
                return response()->json([
                    'success' => false,
                    'message' => $this->pipelineRequiredMessage(),
                ]);
            }

            // Webchat — validate origins format
            $origins = $request->input('allowed_origins', '');
            if (!empty($origins)) {
                $lines = array_filter(array_map('trim', explode("\n", $origins)));
                foreach ($lines as $origin) {
                    if (!filter_var($origin, FILTER_VALIDATE_URL)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Origen inválido: {$origin}. Debe ser una URL completa (https://...).",
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Configuración válida',
                'details' => ['info' => 'El widget se podrá instalar en tu sitio web.'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Campos requeridos faltantes.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Channel wizard validation error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: no se pudo contactar al servidor. Verifica tu red e intenta de nuevo.',
            ]);
        }
    }

    public function wizardStore(Request $request)
    {
        if ($request->user()->cannot('channels.manage')) {
            return response()->json(['success' => false, 'message' => 'Sin permisos.'], 403);
        }

        $tenant = app('tenant');
        $billing = app(BillingService::class);

        if (!$this->canCreateMoreChannels($tenant, $billing)) {
            return response()->json([
                'success' => false,
                'message' => 'Has alcanzado el límite de canales de tu plan.',
            ], 422);
        }

        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:whatsapp,webchat,facebook,instagram',
            'brand_id' => 'nullable|exists:brands,id',
        ];

        switch ($request->input('type')) {
            case 'whatsapp':
                $rules['phone_number_id'] = 'required|string|max:255';
                $rules['waba_id'] = 'required|string|max:255';
                $rules['access_token'] = 'required|string|max:1000';
                $rules['verify_token'] = 'required|string|max:255';
                $rules['app_secret'] = 'required|string|max:255';
                $rules['display_phone'] = 'required|string|max:50';
                break;
            case 'webchat':
                $rules['allowed_origins'] = 'nullable|string';
                break;
            case 'facebook':
                $rules['page_id'] = 'required|string|max:255';
                $rules['page_access_token'] = 'required|string|max:1000';
                $rules['app_secret'] = 'required|string|max:255';
                $rules['verify_token'] = 'required|string|max:255';
                break;
            case 'instagram':
                $rules['instagram_account_id'] = 'required|string|max:255';
                $rules['page_id'] = 'required|string|max:255';
                $rules['page_access_token'] = 'required|string|max:1000';
                $rules['app_secret'] = 'required|string|max:255';
                $rules['verify_token'] = 'required|string|max:255';
                break;
        }

        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($request->input('type') === 'webchat' && !$this->hasLandingPipeline()) {
            return response()->json([
                'success' => false,
                'message' => $this->pipelineRequiredMessage(),
            ], 422);
        }

        $config = $this->buildConfig($request);

        $channel = Channel::create([
            'organization_id' => $tenant->id,
            'brand_id' => $request->input('brand_id') ?: null,
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'configuration' => $config,
            'is_active' => true,
        ]);

        // Build response with integration info
        $webhookUrl = null;
        $widgetSnippet = null;

        if ($channel->type === 'whatsapp') {
            $webhookUrl = url("/api/webhooks/whatsapp/{$channel->uuid}");
        } elseif ($channel->type === 'facebook') {
            $webhookUrl = url("/api/webhooks/facebook/{$channel->uuid}");
        } elseif ($channel->type === 'instagram') {
            $webhookUrl = url("/api/webhooks/instagram/{$channel->uuid}");
        } elseif ($channel->type === 'webchat') {
            $widgetSnippet = $this->generateWidgetSnippet($channel);
        }

        return response()->json([
            'success' => true,
            'channel' => [
                'id' => $channel->id,
                'uuid' => $channel->uuid,
                'name' => $channel->name,
                'type' => $channel->type,
                'verify_token' => $config['verify_token'] ?? null,
            ],
            'webhook_url' => $webhookUrl,
            'widget_snippet' => $widgetSnippet,
            'show_url' => route('settings.channels.show', $channel),
        ]);
    }

    private function canCreateMoreChannels(Organization $tenant, BillingService $billing): bool
    {
        $subscription = $billing->getActiveSubscription($tenant);
        if (!$subscription || !$subscription->hasAccess()) {
            return false;
        }

        $value = $subscription->plan->getFeatureValue('max_channels');
        if (!$value) {
            return false;
        }
        if ($value === 'unlimited') {
            return true;
        }

        $currentCount = Channel::count();
        return $currentCount < (int) $value;
    }

    /**
     * Un canal de webchat necesita un embudo (pipeline) por defecto donde
     * aterricen los contactos. Toda la conversión a deal usa is_default = true.
     */
    private function hasLandingPipeline(): bool
    {
        return Pipeline::where('is_default', true)->exists();
    }

    private function pipelineRequiredMessage(): string
    {
        return 'Primero crea un embudo (pipeline) donde aterricen los contactos del chat web. Ve a CRM › Pipelines, crea uno y vuelve a este paso.';
    }

    // ─── Existing CRUD ──────────────────────────────────────

    private function buildConfig(Request $request, ?Channel $existing = null): array
    {
        $type = $existing ? $existing->type : $request->input('type');

        if ($type === 'whatsapp') {
            $config = [
                'phone_number_id' => $request->input('phone_number_id'),
                'waba_id' => $request->input('waba_id'),
                'app_id' => $request->input('app_id'),
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
