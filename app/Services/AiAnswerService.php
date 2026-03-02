<?php

namespace App\Services;

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
     *
     * @return array{answer: string, sources: array<int, array{id: int, title: string}>}|null
     */
    public function answer(Organization $org, string $question, ?string $channel = null, int $topN = 5): ?array
    {
        if (! $this->isEnabled($org)) {
            return null;
        }

        if (! $this->billing->checkLimit($org, 'ai_queries_monthly')) {
            return ['answer' => 'Monthly AI query limit reached.', 'sources' => []];
        }

        $articles = $this->vectorSearch->search($org, $question, $topN, $channel);

        if ($articles->isEmpty()) {
            return ['answer' => 'No relevant articles found.', 'sources' => []];
        }

        $context = $articles->map(fn ($a, $i) => "[" . ($i + 1) . "] {$a->title}\n{$a->content}")->implode("\n\n---\n\n");

        $systemPrompt = "You are a helpful customer support assistant. Answer the user's question using ONLY the context provided below. If the context doesn't contain enough information, say so. Keep your answer concise and direct.\n\nContext:\n{$context}";

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
            $sources = $articles->map(fn ($a) => ['id' => $a->id, 'title' => $a->title])->values()->all();

            return ['answer' => $answer, 'sources' => $sources];
        } catch (\Throwable $e) {
            Log::error('AiAnswerService: request failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function isEnabled(Organization $org): bool
    {
        if (empty($org->settings['ai_enabled'] ?? false)) {
            return false;
        }

        return $this->billing->checkFeature($org, 'ai_suggestions_enabled');
    }
}
