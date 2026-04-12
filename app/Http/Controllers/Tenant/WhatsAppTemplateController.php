<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class WhatsAppTemplateController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private WhatsAppTemplateService $service)
    {
    }

    public function index(Request $request)
    {
        if (! $request->user()->can('whatsapp_templates.view')) {
            abort(403);
        }

        $tenant = app('tenant');

        $query = WhatsAppTemplate::where('organization_id', $tenant->id)
            ->with('channel');

        if ($channelId = $request->input('channel_id')) {
            $query->where('channel_id', $channelId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', strtoupper($status));
        }

        $templates = $query->orderBy('name')->paginate(20);

        $channels = Channel::where('organization_id', $tenant->id)
            ->where('type', 'whatsapp')
            ->where('is_active', true)
            ->get(['id', 'name']);

        return view('whatsapp-templates.index', compact('templates', 'channels'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', WhatsAppTemplate::class);

        $tenant = app('tenant');
        $channels = Channel::where('organization_id', $tenant->id)
            ->where('type', 'whatsapp')
            ->where('is_active', true)
            ->get(['id', 'name']);

        return view('whatsapp-templates.form', ['template' => null, 'channels' => $channels]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', WhatsAppTemplate::class);

        $data = $request->validate([
            'channel_id' => 'required|integer|exists:channels,id',
            'name' => 'required|string|max:255|regex:/^[a-z][a-z0-9_]*$/',
            'language' => 'required|string|max:10',
            'category' => 'required|in:MARKETING,UTILITY,AUTHENTICATION',
            'header_type' => 'nullable|in:none,text,image,video,document',
            'header_text' => 'nullable|string|max:60',
            'header_handle' => 'nullable|string',
            'header_media_path' => 'nullable|string',
            'body_text' => 'required|string|max:1024',
            'body_example_values' => 'nullable|array',
            'body_example_values.*' => 'string|max:255',
            'footer_text' => 'nullable|string|max:60',
            'buttons' => 'nullable|array|max:3',
            'buttons.*.type' => 'required_with:buttons|in:QUICK_REPLY,URL,PHONE_NUMBER',
            'buttons.*.text' => 'required_with:buttons|string|max:25',
            'buttons.*.url' => 'nullable|url|max:2000',
            'buttons.*.phone_number' => 'nullable|string|max:20',
        ]);

        try {
            $template = $this->service->create(app('tenant')->id, $data['channel_id'], $data);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error al crear plantilla: ' . $e->getMessage());
        }

        return redirect()->route('whatsapp-templates.index')
            ->with('success', 'Plantilla creada. Estado: ' . $template->status);
    }

    public function edit(Request $request, WhatsAppTemplate $template)
    {
        $this->authorize('update', $template);

        $tenant = app('tenant');
        $channels = Channel::where('organization_id', $tenant->id)
            ->where('type', 'whatsapp')
            ->where('is_active', true)
            ->get(['id', 'name']);

        return view('whatsapp-templates.form', compact('template', 'channels'));
    }

    public function update(Request $request, WhatsAppTemplate $template)
    {
        $this->authorize('update', $template);

        $data = $request->validate([
            'channel_id' => 'required|integer|exists:channels,id',
            'name' => 'required|string|max:255|regex:/^[a-z][a-z0-9_]*$/',
            'language' => 'required|string|max:10',
            'category' => 'required|in:MARKETING,UTILITY,AUTHENTICATION',
            'header_type' => 'nullable|in:none,text,image,video,document',
            'header_text' => 'nullable|string|max:60',
            'header_handle' => 'nullable|string',
            'header_media_path' => 'nullable|string',
            'body_text' => 'required|string|max:1024',
            'body_example_values' => 'nullable|array',
            'body_example_values.*' => 'string|max:255',
            'footer_text' => 'nullable|string|max:60',
            'buttons' => 'nullable|array|max:3',
            'buttons.*.type' => 'required_with:buttons|in:QUICK_REPLY,URL,PHONE_NUMBER',
            'buttons.*.text' => 'required_with:buttons|string|max:25',
            'buttons.*.url' => 'nullable|url|max:2000',
            'buttons.*.phone_number' => 'nullable|string|max:20',
        ]);

        try {
            $this->service->update($template, $data);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }

        return redirect()->route('whatsapp-templates.index')
            ->with('success', 'Plantilla actualizada y reenviada a Meta para aprobación.');
    }

    public function destroy(Request $request, WhatsAppTemplate $template)
    {
        $this->authorize('delete', $template);

        try {
            $this->service->delete($template);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }

        return redirect()->route('whatsapp-templates.index')
            ->with('success', 'Plantilla eliminada.');
    }

    public function sync(Request $request)
    {
        if (! $request->user()->can('whatsapp_templates.create')) {
            abort(403);
        }

        $request->validate(['channel_id' => 'required|integer|exists:channels,id']);

        $channel = Channel::findOrFail($request->input('channel_id'));

        if (!$channel->isWhatsApp()) {
            return back()->with('error', 'Solo se pueden sincronizar canales WhatsApp.');
        }

        $count = $this->service->syncFromMeta($channel);

        return back()->with('success', "Se sincronizaron {$count} plantillas desde Meta.");
    }

    public function uploadMedia(Request $request)
    {
        if (! $request->user()->can('whatsapp_templates.create')) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:16384',
            'channel_id' => 'required|integer|exists:channels,id',
        ]);

        $channel = Channel::findOrFail($request->input('channel_id'));
        $result = $this->service->uploadHeaderMedia($request->file('file'), $channel);

        return response()->json($result);
    }

    public function approvedForChannel(Request $request)
    {
        if (! $request->user()->can('whatsapp_templates.view')) {
            return response()->json([], 403);
        }

        $request->validate(['channel_id' => 'required|integer']);

        $templates = $this->service->getApprovedForChannel($request->input('channel_id'));

        return response()->json($templates->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'language' => $t->language,
            'category' => $t->category,
            'header_type' => $t->header_type,
            'body_text' => $t->body_text,
            'footer_text' => $t->footer_text,
            'buttons' => $t->buttons,
            'variable_count' => $t->getVariableCount(),
        ]));
    }
}
