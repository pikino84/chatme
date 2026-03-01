<?php

namespace Tests\Feature;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\KbVersion;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanFeatureValue;
use App\Models\User;
use App\Services\BillingService;
use App\Services\KnowledgeBaseService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $author;
    private KnowledgeBaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->author = User::factory()->create(['organization_id' => $this->org->id]);
        $this->service = new KnowledgeBaseService(new BillingService());

        // Set up a plan with kb_articles_limit
        $plan = Plan::factory()->create();
        $feature = PlanFeature::create([
            'code' => 'kb_articles_limit',
            'description' => 'Maximum KB articles',
            'type' => 'limit',
        ]);
        PlanFeatureValue::create([
            'plan_id' => $plan->id,
            'plan_feature_id' => $feature->id,
            'value' => '5',
        ]);
        (new BillingService())->subscribe($this->org, $plan);
    }

    // --- createArticle ---

    public function test_create_article(): void
    {
        $article = $this->service->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'Getting Started',
            'slug' => 'getting-started',
            'content' => 'Welcome to our knowledge base.',
        ], $this->author);

        $this->assertEquals('Getting Started', $article->title);
        $this->assertEquals('draft', $article->status);
        $this->assertEquals($this->author->id, $article->created_by);
        $this->assertEquals($this->org->id, $article->organization_id);
    }

    public function test_create_article_creates_initial_version(): void
    {
        $article = $this->service->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'content' => 'Some content.',
        ], $this->author);

        $versions = $article->versions;
        $this->assertCount(1, $versions);
        $this->assertEquals(1, $versions->first()->version_number);
        $this->assertEquals('Initial version', $versions->first()->change_summary);
    }

    public function test_create_article_with_category(): void
    {
        $category = KbCategory::factory()->create(['organization_id' => $this->org->id]);

        $article = $this->service->createArticle([
            'organization_id' => $this->org->id,
            'kb_category_id' => $category->id,
            'title' => 'Categorized Article',
            'slug' => 'categorized-article',
            'content' => 'Content here.',
        ], $this->author);

        $this->assertEquals($category->id, $article->kb_category_id);
    }

    public function test_create_article_blocked_when_limit_reached(): void
    {
        // Create 5 articles (the limit)
        for ($i = 0; $i < 5; $i++) {
            KbArticle::factory()->create([
                'organization_id' => $this->org->id,
                'created_by' => $this->author->id,
            ]);
        }

        $this->expectException(\OverflowException::class);

        $this->service->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'One Too Many',
            'slug' => 'one-too-many',
            'content' => 'Should fail.',
        ], $this->author);
    }

    // --- updateArticle ---

    public function test_update_article(): void
    {
        $article = $this->service->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'Original Title',
            'slug' => 'original-title',
            'content' => 'Original content.',
        ], $this->author);

        $editor = User::factory()->create(['organization_id' => $this->org->id]);

        $updated = $this->service->updateArticle($article, [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
        ], $editor);

        $this->assertEquals('Updated Title', $updated->title);
        $this->assertEquals($editor->id, $updated->updated_by);
    }

    public function test_update_article_creates_new_version(): void
    {
        $article = $this->service->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'V1 Title',
            'slug' => 'v1-title',
            'content' => 'V1 content.',
        ], $this->author);

        $this->service->updateArticle($article, [
            'title' => 'V2 Title',
            'content' => 'V2 content.',
            'change_summary' => 'Updated title and content',
        ], $this->author);

        $versions = $article->fresh()->versions()->orderBy('version_number')->get();
        $this->assertCount(2, $versions);
        $this->assertEquals(1, $versions->first()->version_number);
        $this->assertEquals(2, $versions->last()->version_number);
        $this->assertEquals('Updated title and content', $versions->last()->change_summary);
    }

    public function test_version_preserves_history(): void
    {
        $article = $this->service->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'Original',
            'slug' => 'version-history',
            'content' => 'First version.',
        ], $this->author);

        $this->service->updateArticle($article, [
            'title' => 'Modified',
            'content' => 'Second version.',
        ], $this->author);

        $this->service->updateArticle($article->fresh(), [
            'title' => 'Final',
            'content' => 'Third version.',
        ], $this->author);

        $versions = $article->fresh()->versions()->orderBy('version_number')->get();
        $this->assertCount(3, $versions);
        $this->assertEquals('Original', $versions[0]->title);
        $this->assertEquals('Modified', $versions[1]->title);
        $this->assertEquals('Final', $versions[2]->title);
    }

    // --- publishArticle ---

    public function test_publish_article(): void
    {
        $article = $this->service->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'Draft Article',
            'slug' => 'draft-article',
            'content' => 'Will be published.',
        ], $this->author);

        $published = $this->service->publishArticle($article, $this->author);

        $this->assertTrue($published->isPublished());
        $this->assertNotNull($published->published_at);
    }

    // --- archiveArticle ---

    public function test_archive_article(): void
    {
        $article = $this->service->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'Active Article',
            'slug' => 'active-article',
            'content' => 'Will be archived.',
        ], $this->author);

        $archived = $this->service->archiveArticle($article, $this->author);

        $this->assertTrue($archived->isArchived());
    }

    // --- deleteArticle ---

    public function test_delete_article(): void
    {
        $article = $this->service->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'To Delete',
            'slug' => 'to-delete',
            'content' => 'Will be deleted.',
        ], $this->author);

        $articleId = $article->id;
        $this->service->deleteArticle($article);

        $this->assertDatabaseMissing('kb_articles', ['id' => $articleId]);
    }

    public function test_delete_article_cascades_versions(): void
    {
        $article = $this->service->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'With Versions',
            'slug' => 'with-versions',
            'content' => 'Has versions.',
        ], $this->author);

        $this->service->updateArticle($article, [
            'content' => 'Updated content.',
        ], $this->author);

        $articleId = $article->id;
        $this->service->deleteArticle($article);

        $this->assertEquals(0, KbVersion::withoutGlobalScopes()->where('kb_article_id', $articleId)->count());
    }

    // --- getPublishedArticles ---

    public function test_get_published_articles(): void
    {
        KbArticle::factory()->published()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);
        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
            'status' => 'draft',
        ]);

        $articles = $this->service->getPublishedArticles($this->org);

        $this->assertCount(1, $articles);
    }

    public function test_get_published_articles_by_channel(): void
    {
        KbArticle::factory()->published()->visibleOnWebchat()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);
        KbArticle::factory()->published()->visibleOnWhatsApp()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);
        KbArticle::factory()->published()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
            'visible_on_webchat' => false,
            'visible_on_whatsapp' => false,
        ]);

        $webchat = $this->service->getPublishedArticles($this->org, 'webchat');
        $whatsapp = $this->service->getPublishedArticles($this->org, 'whatsapp');

        $this->assertCount(1, $webchat);
        $this->assertCount(1, $whatsapp);
    }

    public function test_get_published_articles_ordered_by_priority(): void
    {
        KbArticle::factory()->published()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
            'title' => 'Low Priority',
            'priority' => 1,
        ]);
        KbArticle::factory()->published()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
            'title' => 'High Priority',
            'priority' => 10,
        ]);

        $articles = $this->service->getPublishedArticles($this->org);

        $this->assertEquals('High Priority', $articles->first()->title);
    }
}
