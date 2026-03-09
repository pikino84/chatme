<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Organization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAnswerService
{
    public function __construct(
        private VectorSearchService $vectorSearch,
        private BillingService $billing,
    ) {}

    /**
     * Generate an AI-powered answer using RAG (Retrieve + Generate).
     * Supports brand-aware context: prioritizes the conversation's brand KB
     * but can cross-reference other brands within the same organization.
     *
     * @return array{answer: string, sources: array<int, array{id: int, title: string, brand: string|null}>}|null
     */
    public function answer(Organization $org, string $question, ?string $channel = null, int $topN = 5, ?int $brandId = null): ?array
    {
        if (! $this->isEnabled($org)) {
            return null;
        }

        if (! $this->billing->checkLimit($org, 'ai_queries_monthly')) {
            return ['answer' => 'Monthly AI query limit reached.', 'sources' => []];
        }

        $articles = $this->vectorSearch->search($org, $question, $topN, $channel, $brandId);

        if ($articles->isEmpty()) {
            return ['answer' => 'No relevant articles found.', 'sources' => []];
        }

        $context = $articles->map(function ($a, $i) {
            $brandLabel = $a->brand?->name ?? 'General';
            return "[" . ($i + 1) . "] [{$brandLabel}] {$a->title}\n{$a->content}";
        })->implode("\n\n---\n\n");

        $systemPrompt = $this->buildSystemPrompt($org, $brandId, $context);

        $apiKey = config('services.openai.api_key');
        $model = $org->settings['ai_model'] ?? config('services.openai.chat_model', 'gpt-4o-mini');
        $temperature = (float) ($org->settings['ai_temperature'] ?? 0.3);

        if (empty($apiKey)) {
            Log::warning('AiAnswerService: OpenAI API key not configured.');
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => $temperature,
                    'max_tokens' => 500,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $question],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('AiAnswerService: API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $this->billing->incrementUsage($org, 'ai_queries_monthly');

            $answer = $response->json('choices.0.message.content', '');
            $sources = $articles->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'brand' => $a->brand?->name,
            ])->values()->all();

            return ['answer' => $answer, 'sources' => $sources];
        } catch (\Throwable $e) {
            Log::error('AiAnswerService: request failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildSystemPrompt(Organization $org, ?int $brandId, string $context): string
    {
        $base = "You are a helpful customer support assistant. Answer the user's question using ONLY the context provided below. If the context doesn't contain enough information, say so. Keep your answer concise and direct.";

        // Add brand-specific AI context if available
        if ($brandId) {
            $brand = Brand::withoutGlobalScopes()->find($brandId);
            if ($brand && $brand->getAiContext()) {
                $base .= "\n\nBrand context for {$brand->name}:\n{$brand->getAiContext()}";
            }
        }

        $base .= "\n\nIMPORTANT: The context may include articles from multiple brands within the same organization. Each article is labeled with its brand name in brackets. When answering, clearly identify which brand or location the information belongs to. If asked about a specific brand, prioritize that brand's articles.";

        $base .= "\n\nContext:\n{$context}";

        return $base;
    }

    public function isEnabled(Organization $org): bool
    {
        if (empty($org->settings['ai_enabled'] ?? false)) {
            return false;
        }

        return $this->billing->checkFeature($org, 'ai_suggestions_enabled');
    }
}
