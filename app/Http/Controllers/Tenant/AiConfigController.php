<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\AiAnswerService;
use App\Services\BillingService;
use App\Services\EmbeddingService;
use App\Services\VectorSearchService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class AiConfigController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request, BillingService $billing, VectorSearchService $vectorSearch, EmbeddingService $embeddingService)
    {
        if (! $request->user()->can('settings.update')) {
            abort(403);
        }

        $org = app('tenant');
        $featureEnabled = $billing->checkFeature($org, 'ai_suggestions_enabled');
        $vectorAvailable = $vectorSearch->isAvailable();
        $apiConfigured = $embeddingService->isConfigured();
        $usage = $billing->getUsage($org, 'ai_queries_monthly');

        return view('settings.ai', compact('org', 'featureEnabled', 'vectorAvailable', 'apiConfigured', 'usage'));
    }

    public function update(Request $request)
    {
        if (! $request->user()->can('settings.update')) {
            abort(403);
        }

        $request->validate([
            'ai_enabled' => 'nullable|boolean',
            'ai_model' => 'nullable|string|in:gpt-4o-mini,gpt-4o,gpt-4-turbo',
            'ai_temperature' => 'nullable|numeric|min:0|max:1',
        ]);

        $org = app('tenant');
        $settings = $org->settings ?? [];

        $settings['ai_enabled'] = $request->has('ai_enabled');
        $settings['ai_model'] = $request->input('ai_model', 'gpt-4o-mini');
        $settings['ai_temperature'] = $request->input('ai_temperature', 0.3);

        $org->settings = $settings;
        $org->save();

        return redirect()->route('settings.ai')->with('success', 'AI settings updated.');
    }
}
