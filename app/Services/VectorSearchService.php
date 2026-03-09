<?php

namespace App\Services;

use App\Models\KbArticle;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class VectorSearchService
{
    public function __construct(
        private EmbeddingService $embeddingService,
    ) {}

    /**
     * Search published KB articles by semantic similarity.
     * Supports brand-aware cross-brand search with priority weighting.
     *
     * @param  int|null  $brandId  The brand of the current conversation/channel
     * @return Collection<int, KbArticle> Articles sorted by relevance, each with a `similarity_score` attribute.
     */
    public function search(Organization $org, string $query, int $limit = 5, ?string $channel = null, ?int $brandId = null): Collection
    {
        if (! $this->isAvailable()) {
            return $this->fallbackSearch($org, $query, $limit, $channel, $brandId);
        }

        $queryEmbedding = $this->embeddingService->generate($query);

        if (! $queryEmbedding) {
            return $this->fallbackSearch($org, $query, $limit, $channel, $brandId);
        }

        $vector = '[' . implode(',', $queryEmbedding) . ']';

        // Fetch more results to allow re-ranking by brand priority
        $fetchLimit = $brandId ? $limit * 3 : $limit;

        $sql = "SELECT id, brand_id, (embedding <=> ?::vector) AS distance
                FROM kb_articles
                WHERE organization_id = ?
                  AND status = 'published'
                  AND embedding IS NOT NULL";

        $bindings = [$vector, $org->id];

        if ($channel) {
            $allowed = ['webchat', 'whatsapp', 'instagram', 'facebook'];
            if (in_array($channel, $allowed, true)) {
                $sql .= " AND visible_on_{$channel} = true";
            }
        }

        $sql .= " ORDER BY distance ASC LIMIT ?";
        $bindings[] = $fetchLimit;

        try {
            $rows = DB::select($sql, $bindings);
        } catch (\Throwable $e) {
            Log::error('VectorSearchService: query failed', ['error' => $e->getMessage()]);
            return $this->fallbackSearch($org, $query, $limit, $channel, $brandId);
        }

        if (empty($rows)) {
            return collect();
        }

        $ids = array_column($rows, 'id');
        $distances = collect($rows)->keyBy('id');

        $articles = KbArticle::withoutGlobalScopes()
            ->with('brand:id,name,color')
            ->whereIn('id', $ids)
            ->get();

        // Apply brand-aware scoring
        foreach ($articles as $article) {
            $dist = $distances[$article->id]->distance ?? 1;
            $baseSimilarity = 1 - $dist;

            $weight = $this->getBrandWeight($article->brand_id, $brandId);
            $article->setAttribute('similarity_score', round($baseSimilarity * $weight, 4));
        }

        return $articles->sortByDesc('similarity_score')->take($limit)->values();
    }

    /**
     * Get brand priority weight for scoring.
     *
     * Priority: same brand (1.0) > global/null (0.9) > other brands (0.8)
     */
    private function getBrandWeight(?int $articleBrandId, ?int $conversationBrandId): float
    {
        if (! $conversationBrandId) {
            return 1.0; // No brand context — no weighting
        }

        if ($articleBrandId === $conversationBrandId) {
            return 1.0; // Same brand — highest priority
        }

        if ($articleBrandId === null) {
            return 0.9; // Global article — high priority
        }

        return 0.8; // Other brand — lower but still included
    }

    public function isAvailable(): bool
    {
        try {
            return in_array('embedding', Schema::getColumnListing('kb_articles'));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Keyword-based fallback when pgvector is unavailable.
     */
    private function fallbackSearch(Organization $org, string $query, int $limit, ?string $channel = null, ?int $brandId = null): Collection
    {
        $fetchLimit = $brandId ? $limit * 3 : $limit;

        $q = KbArticle::withoutGlobalScopes()
            ->with('brand:id,name,color')
            ->where('organization_id', $org->id)
            ->where('status', 'published')
            ->where(function ($qb) use ($query) {
                $qb->whereRaw('LOWER(title) LIKE ?', ['%' . mb_strtolower($query) . '%'])
                    ->orWhereRaw('LOWER(content) LIKE ?', ['%' . mb_strtolower($query) . '%']);
            });

        if ($channel) {
            $allowed = ['webchat', 'whatsapp', 'instagram', 'facebook'];
            if (in_array($channel, $allowed, true)) {
                $q->where("visible_on_{$channel}", true);
            }
        }

        $articles = $q->orderByDesc('priority')->limit($fetchLimit)->get();

        if (! $brandId) {
            return $articles->take($limit);
        }

        // Re-rank by brand priority
        return $articles->sortByDesc(function ($article) use ($brandId) {
            $brandWeight = $this->getBrandWeight($article->brand_id, $brandId);
            return ($article->priority * $brandWeight);
        })->take($limit)->values();
    }
}
