<?php

namespace App\Jobs;

use App\Models\KbArticle;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateArticleEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $articleId,
        public readonly int $organizationId,
    ) {
        $this->onQueue('low');
    }

    public function handle(EmbeddingService $embeddingService): void
    {
        $article = KbArticle::withoutGlobalScopes()
            ->where('id', $this->articleId)
            ->where('organization_id', $this->organizationId)
            ->first();

        if (! $article || ! $article->isPublished()) {
            Log::info('GenerateArticleEmbedding: article not found or not published', [
                'article_id' => $this->articleId,
            ]);
            return;
        }

        if (! $this->hasEmbeddingColumn()) {
            Log::info('GenerateArticleEmbedding: embedding column not available (pgvector not installed).');
            return;
        }

        $text = $article->title . "\n\n" . $article->content;
        $embedding = $embeddingService->generate($text);

        if (! $embedding) {
            Log::warning('GenerateArticleEmbedding: failed to generate embedding', [
                'article_id' => $this->articleId,
            ]);
            return;
        }

        $vector = '[' . implode(',', $embedding) . ']';

        DB::table('kb_articles')
            ->where('id', $article->id)
            ->update(['embedding' => $vector]);

        Log::info('GenerateArticleEmbedding: embedding stored', [
            'article_id' => $article->id,
        ]);
    }

    private function hasEmbeddingColumn(): bool
    {
        try {
            return in_array('embedding', \Illuminate\Support\Facades\Schema::getColumnListing('kb_articles'));
        } catch (\Throwable) {
            return false;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateArticleEmbedding: failed permanently', [
            'article_id' => $this->articleId,
            'error' => $exception->getMessage(),
        ]);
    }
}
