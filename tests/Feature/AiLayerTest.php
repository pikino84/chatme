<?php

namespace Tests\Feature;

use App\Jobs\GenerateArticleEmbedding;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanFeatureValue;
use App\Models\User;
use App\Services\AiAnswerService;
use App\Services\BillingService;
use App\Services\EmbeddingService;
use App\Services\KnowledgeBaseService;
use App\Services\VectorSearchService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiLayerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Plan $plan;
    private User $orgAdmin;
    private User $agent;
    private KbCategory $category;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::factory()->create(['settings' => ['ai_enabled' => true]]);

        $this->plan = Plan::factory()->create();
        OrganizationSubscription::factory()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
        ]);

        $this->setupBillingFeatures();

        $this->category = KbCategory::create([
            'organization_id' => $this->org->id,
            'name' => 'General',
            'position' => 0,
            'is_active' => true,
        ]);

        $this->orgAdmin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->orgAdmin->assignRole('org_admin');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        $this->domain = 'http://app.' . config('app.base_domain');
        app()->instance('tenant', $this->org);
    }

    private function setupBillingFeatures(): void
    {
        $kbFeature = PlanFeature::create([
            'code' => 'kb_articles_limit',
            'description' => 'KB articles limit',
            'type' => 'limit',
        ]);
        PlanFeatureValue::create([
            'plan_id' => $this->plan->id,
            'plan_feature_id' => $kbFeature->id,
            'value' => 'unlimited',
        ]);

        $aiFeature = PlanFeature::create([
            'code' => 'ai_suggestions_enabled',
            'description' => 'AI-powered answers',
            'type' => 'boolean',
        ]);
        PlanFeatureValue::create([
            'plan_id' => $this->plan->id,
            'plan_feature_id' => $aiFeature->id,
            'value' => 'true',
        ]);

        $aiLimit = PlanFeature::create([
            'code' => 'ai_queries_monthly',
            'description' => 'Monthly AI queries',
            'type' => 'limit',
        ]);
        PlanFeatureValue::create([
            'plan_id' => $this->plan->id,
            'plan_feature_id' => $aiLimit->id,
            'value' => '100',
        ]);
    }

    private function createPublishedArticle(array $overrides = []): KbArticle
    {
        return KbArticle::create(array_merge([
            'organization_id' => $this->org->id,
            'kb_category_id' => $this->category->id,
            'created_by' => $this->orgAdmin->id,
            'title' => 'How to reset your password',
            'content' => 'Go to Settings > Security > Reset Password. Enter your current password and new password.',
            'status' => 'published',
            'priority' => 10,
            'visible_on_webchat' => true,
        ], $overrides));
    }

    // --- Test 1: Embedding job dispatched on publish ---

    public function test_publish_dispatches_embedding_job(): void
    {
        Queue::fake();

        $article = KbArticle::create([
            'organization_id' => $this->org->id,
            'kb_category_id' => $this->category->id,
            'created_by' => $this->orgAdmin->id,
            'title' => 'Test Article',
            'content' => 'Some content.',
            'status' => 'draft',
            'priority' => 0,
        ]);

        $service = app(KnowledgeBaseService::class);
        $service->publishArticle($article, $this->orgAdmin);

        Queue::assertPushed(GenerateArticleEmbedding::class, function ($job) use ($article) {
            return $job->articleId === $article->id
                && $job->organizationId === $this->org->id;
        });
    }

    // --- Test 2: Embedding job on low queue ---

    public function test_embedding_job_dispatched_on_low_queue(): void
    {
        Queue::fake();

        $article = KbArticle::create([
            'organization_id' => $this->org->id,
            'kb_category_id' => $this->category->id,
            'created_by' => $this->orgAdmin->id,
            'title' => 'Queue Test',
            'content' => 'Content.',
            'status' => 'draft',
            'priority' => 0,
        ]);

        $service = app(KnowledgeBaseService::class);
        $service->publishArticle($article, $this->orgAdmin);

        Queue::assertPushed(GenerateArticleEmbedding::class, function ($job) {
            return $job->queue === 'low';
        });
    }

    // --- Test 3: VectorSearchService fallback returns keyword results ---

    public function test_vector_search_fallback_returns_keyword_matches(): void
    {
        $this->createPublishedArticle();
        $this->createPublishedArticle([
            'title' => 'Billing FAQ',
            'content' => 'How billing works.',
        ]);

        $vectorSearch = new VectorSearchService(new EmbeddingService());
        $results = $vectorSearch->search($this->org, 'password');

        $this->assertCount(1, $results);
        $this->assertEquals('How to reset your password', $results->first()->title);
    }

    // --- Test 4: Fallback search respects tenant isolation ---

    public function test_vector_search_respects_tenant_isolation(): void
    {
        $this->createPublishedArticle();

        $otherOrg = Organization::factory()->create();
        KbArticle::create([
            'organization_id' => $otherOrg->id,
            'kb_category_id' => $this->category->id,
            'created_by' => $this->orgAdmin->id,
            'title' => 'Other org password guide',
            'content' => 'Password reset for other org.',
            'status' => 'published',
            'priority' => 10,
        ]);

        $vectorSearch = new VectorSearchService(new EmbeddingService());
        $results = $vectorSearch->search($this->org, 'password');

        $this->assertCount(1, $results);
        $this->assertEquals($this->org->id, $results->first()->organization_id);
    }

    // --- Test 5: AiAnswerService checks billing feature ---

    public function test_ai_answer_disabled_when_feature_off(): void
    {
        $this->org->update(['settings' => ['ai_enabled' => false]]);

        $aiService = app(AiAnswerService::class);
        $result = $aiService->answer($this->org, 'How to reset password?');

        $this->assertNull($result);
    }

    // --- Test 6: AiAnswerService enabled check ---

    public function test_ai_answer_enabled_when_feature_and_setting_on(): void
    {
        $aiService = app(AiAnswerService::class);
        $this->assertTrue($aiService->isEnabled($this->org));
    }

    // --- Test 7: AI config screen accessible by org_admin ---

    public function test_org_admin_can_view_ai_config(): void
    {
        $response = $this->actingAs($this->orgAdmin)->get("{$this->domain}/settings/ai");

        $response->assertOk();
        $response->assertViewIs('settings.ai');
    }

    // --- Test 8: AI config can be updated ---

    public function test_org_admin_can_update_ai_config(): void
    {
        $response = $this->actingAs($this->orgAdmin)->post("{$this->domain}/settings/ai", [
            'ai_enabled' => '1',
            'ai_model' => 'gpt-4o',
            'ai_temperature' => '0.5',
        ]);

        $response->assertRedirect(route('settings.ai'));

        $settings = $this->org->fresh()->settings;
        $this->assertTrue($settings['ai_enabled']);
        $this->assertEquals('gpt-4o', $settings['ai_model']);
        $this->assertEquals(0.5, $settings['ai_temperature']);
    }

    // --- Test 9: Agent cannot access AI config ---

    public function test_agent_cannot_access_ai_config(): void
    {
        $response = $this->actingAs($this->agent)->get("{$this->domain}/settings/ai");

        $response->assertForbidden();
    }

    // --- Test 10: EmbeddingService returns null when not configured ---

    public function test_embedding_service_returns_null_without_api_key(): void
    {
        config(['services.openai.api_key' => '']);

        $service = new EmbeddingService();
        $this->assertFalse($service->isConfigured());
        $this->assertNull($service->generate('test'));
    }
}
