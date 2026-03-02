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
     *
     * @return Collection<int, KbArticle> Articles sorted by relevance, each with a `similarity_score` attribute.
     */
    public function search(Organization $org, string $query, int $limit = 5, ?string $channel = null): Collection
    {
        if (! $this->isAvailable()) {
            return $this->fallbackSearch($org, $query, $limit, $channel);
        }

        $queryEmbedding = $this->embeddingService->generate($query);

        if (! $queryEmbedding) {
            return $this->fallbackSearch($org, $query, $limit, $channel);
        }

        $vector = '[' . implode(',', $queryEmbedding) . ']';

        $sql = "SELECT id, (embedding <=> ?::vector) AS distance
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
        $bindings[] = $limit;

        try {
            $rows = DB::select($sql, $bindings);
        } catch (\Throwable $e) {
            Log::error('VectorSearchService: query failed', ['error' => $e->getMessage()]);
            return $this->fallbackSearch($org, $query, $limit, $channel);
        }

        if (empty($rows)) {
            return collect();
        }

        $ids = array_column($rows, 'id');
        $distances = collect($rows)->keyBy('id');

        $articles = KbArticle::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($a) => $distances[$a->id]->distance ?? 1);

        foreach ($articles as $article) {
            $dist = $distances[$article->id]->distance ?? 1;
            $article->setAttribute('similarity_score', round(1 - $dist, 4));
        }

        return $articles->values();
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
    private function fallbackSearch(Organization $org, string $query, int $limit, ?string $channel = null): Collection
    {
        $q = KbArticle::withoutGlobalScopes()
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

        return $q->orderByDesc('priority')->limit($limit)->get();
    }
}
