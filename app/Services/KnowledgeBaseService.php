<?php

namespace App\Services;

use App\Jobs\GenerateArticleEmbedding;
use App\Models\KbArticle;
use App\Models\KbVersion;
use App\Models\Organization;
use App\Models\User;

class KnowledgeBaseService
{
    public function __construct(
        private BillingService $billingService,
    ) {}

    public function createArticle(array $data, User $author): KbArticle
    {
        $org = Organization::withoutGlobalScopes()->findOrFail($data['organization_id']);

        if (!$this->canCreateMoreArticles($org)) {
            throw new \OverflowException('KB articles limit reached for this plan.');
        }

        $data['created_by'] = $author->id;
        $data['status'] = $data['status'] ?? 'draft';

        $article = KbArticle::create($data);

        KbVersion::create([
            'organization_id' => $article->organization_id,
            'kb_article_id' => $article->id,
            'version_number' => 1,
            'title' => $article->title,
            'content' => $article->content,
            'changed_by' => $author->id,
            'change_summary' => 'Initial version',
        ]);

        return $article;
    }

    public function updateArticle(KbArticle $article, array $data, User $editor): KbArticle
    {
        $article->update(array_merge($data, [
            'updated_by' => $editor->id,
        ]));

        $latestVersion = $article->versions()
            ->orderByDesc('version_number')
            ->first();

        $nextVersion = $latestVersion ? $latestVersion->version_number + 1 : 1;

        KbVersion::create([
            'organization_id' => $article->organization_id,
            'kb_article_id' => $article->id,
            'version_number' => $nextVersion,
            'title' => $article->title,
            'content' => $article->content,
            'changed_by' => $editor->id,
            'change_summary' => $data['change_summary'] ?? null,
        ]);

        return $article->fresh();
    }

    public function publishArticle(KbArticle $article, User $actor): KbArticle
    {
        $article->update([
            'status' => 'published',
            'published_at' => now(),
            'updated_by' => $actor->id,
        ]);

        GenerateArticleEmbedding::dispatch($article->id, $article->organization_id);

        return $article->fresh();
    }

    public function archiveArticle(KbArticle $article, User $actor): KbArticle
    {
        $article->update([
            'status' => 'archived',
            'updated_by' => $actor->id,
        ]);

        return $article->fresh();
    }

    public function deleteArticle(KbArticle $article): void
    {
        $article->delete();
    }

    public function getPublishedArticles(Organization $org, ?string $channel = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = KbArticle::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'published');

        if ($channel) {
            $column = "visible_on_{$channel}";
            $query->where($column, true);
        }

        return $query->orderByDesc('priority')->get();
    }

    private function canCreateMoreArticles(Organization $org): bool
    {
        $subscription = $this->billingService->getActiveSubscription($org);

        if (!$subscription || !$subscription->hasAccess()) {
            return false;
        }

        $limit = $subscription->plan->getFeatureValue('kb_articles_limit');

        if (!$limit) {
            return false;
        }

        if (strtolower($limit) === 'unlimited') {
            return true;
        }

        $currentCount = KbArticle::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->count();

        return $currentCount < (int) $limit;
    }
}
