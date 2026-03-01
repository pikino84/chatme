<?php

namespace Tests\Feature;

use App\Models\KbArticle;
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

class KbBillingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $author;
    private BillingService $billingService;
    private KnowledgeBaseService $kbService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->author = User::factory()->create(['organization_id' => $this->org->id]);
        $this->billingService = new BillingService();
        $this->kbService = new KnowledgeBaseService($this->billingService);
    }

    private function createPlanWithLimit(string $limit): Plan
    {
        $plan = Plan::factory()->create();
        $feature = PlanFeature::firstOrCreate(
            ['code' => 'kb_articles_limit'],
            ['description' => 'Maximum KB articles', 'type' => 'limit']
        );
        PlanFeatureValue::create([
            'plan_id' => $plan->id,
            'plan_feature_id' => $feature->id,
            'value' => $limit,
        ]);

        return $plan;
    }

    public function test_can_create_articles_within_limit(): void
    {
        $plan = $this->createPlanWithLimit('3');
        $this->billingService->subscribe($this->org, $plan);

        $article = $this->kbService->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'First Article',
            'slug' => 'first-article',
            'content' => 'Content.',
        ], $this->author);

        $this->assertNotNull($article->id);
    }

    public function test_blocked_when_limit_exceeded(): void
    {
        $plan = $this->createPlanWithLimit('2');
        $this->billingService->subscribe($this->org, $plan);

        // Create 2 articles to hit the limit
        for ($i = 0; $i < 2; $i++) {
            KbArticle::factory()->create([
                'organization_id' => $this->org->id,
                'created_by' => $this->author->id,
            ]);
        }

        $this->expectException(\OverflowException::class);

        $this->kbService->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'Third Article',
            'slug' => 'third-article',
            'content' => 'Should fail.',
        ], $this->author);
    }

    public function test_unlimited_plan_allows_unlimited_articles(): void
    {
        $plan = $this->createPlanWithLimit('unlimited');
        $this->billingService->subscribe($this->org, $plan);

        // Create many articles
        for ($i = 0; $i < 10; $i++) {
            KbArticle::factory()->create([
                'organization_id' => $this->org->id,
                'created_by' => $this->author->id,
            ]);
        }

        // Should not throw
        $article = $this->kbService->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'Eleventh Article',
            'slug' => 'eleventh-article',
            'content' => 'No limit.',
        ], $this->author);

        $this->assertNotNull($article->id);
    }

    public function test_blocked_without_subscription(): void
    {
        // No subscription for org
        $this->expectException(\OverflowException::class);

        $this->kbService->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'No Sub Article',
            'slug' => 'no-sub-article',
            'content' => 'Should fail.',
        ], $this->author);
    }

    public function test_delete_frees_up_limit(): void
    {
        $plan = $this->createPlanWithLimit('2');
        $this->billingService->subscribe($this->org, $plan);

        $article1 = $this->kbService->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'Article 1',
            'slug' => 'article-1',
            'content' => 'Content.',
        ], $this->author);

        $this->kbService->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'Article 2',
            'slug' => 'article-2',
            'content' => 'Content.',
        ], $this->author);

        // Delete one to free up space
        $this->kbService->deleteArticle($article1);

        // Should now be able to create again
        $article3 = $this->kbService->createArticle([
            'organization_id' => $this->org->id,
            'title' => 'Article 3',
            'slug' => 'article-3',
            'content' => 'Content.',
        ], $this->author);

        $this->assertNotNull($article3->id);
    }

    public function test_seeder_has_kb_articles_limit_feature(): void
    {
        $this->seed(\Database\Seeders\PlansAndFeaturesSeeder::class);

        $feature = PlanFeature::where('code', 'kb_articles_limit')->first();
        $this->assertNotNull($feature);
        $this->assertEquals('limit', $feature->type);
    }

    public function test_seeder_starter_plan_has_20_articles(): void
    {
        $this->seed(\Database\Seeders\PlansAndFeaturesSeeder::class);

        $plan = Plan::where('slug', 'starter')->first();
        $value = $plan->getFeatureValue('kb_articles_limit');
        $this->assertEquals('20', $value);
    }

    public function test_seeder_professional_plan_has_200_articles(): void
    {
        $this->seed(\Database\Seeders\PlansAndFeaturesSeeder::class);

        $plan = Plan::where('slug', 'professional')->first();
        $value = $plan->getFeatureValue('kb_articles_limit');
        $this->assertEquals('200', $value);
    }

    public function test_seeder_enterprise_plan_has_unlimited_articles(): void
    {
        $this->seed(\Database\Seeders\PlansAndFeaturesSeeder::class);

        $plan = Plan::where('slug', 'enterprise')->first();
        $value = $plan->getFeatureValue('kb_articles_limit');
        $this->assertEquals('unlimited', $value);
    }
}
