<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.openai.api_key', '');
        $this->model = (string) config('services.openai.embedding_model', 'text-embedding-3-small');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Generate an embedding vector for the given text.
     *
     * @return float[]|null Returns 1536-dimension vector or null on failure.
     */
    public function generate(string $text): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('EmbeddingService: OpenAI API key not configured.');
            return null;
        }

        $text = mb_substr(trim($text), 0, 8000);

        if (empty($text)) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $text,
                ]);

            if ($response->failed()) {
                Log::error('EmbeddingService: API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('data.0.embedding');
        } catch (\Throwable $e) {
            Log::error('EmbeddingService: request failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
