<?php

namespace Tests\Feature;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanFeatureValue;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Broadcasting\BroadcastEvent;
use Tests\TestCase;

class KnowledgeBaseUiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private KbCategory $category;
    private User $orgAdmin;
    private User $agent;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::factory()->create();

        // Set up billing so createArticle works
        $plan = Plan::factory()->create();
        OrganizationSubscription::factory()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $plan->id,
        ]);
        $feature = PlanFeature::create([
            'code' => 'kb_articles_limit',
            'description' => 'KB articles limit',
            'type' => 'limit',
        ]);
        PlanFeatureValue::create([
            'plan_id' => $plan->id,
            'plan_feature_id' => $feature->id,
            'value' => 'unlimited',
        ]);

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
        Event::fake([BroadcastEvent::class]);
    }

    private function kbUrl(string $path = ''): string
    {
        return "{$this->domain}/kb{$path}";
    }

    private function createArticle(array $overrides = []): KbArticle
    {
        return KbArticle::create(array_merge([
            'organization_id' => $this->org->id,
            'kb_category_id' => $this->category->id,
            'created_by' => $this->orgAdmin->id,
            'title' => 'Test Article',
            'content' => 'Test content for the article.',
            'status' => 'draft',
            'priority' => 0,
        ], $overrides));
    }

    public function test_org_admin_can_view_categories(): void
    {
        $response = $this->actingAs($this->orgAdmin)->get($this->kbUrl('/categories'));

        $response->assertOk();
        $response->assertViewIs('kb.categories');
    }

    public function test_org_admin_can_create_category(): void
    {
        $response = $this->actingAs($this->orgAdmin)->post($this->kbUrl('/categories'), [
            'name' => 'FAQ',
            'description' => 'Frequently asked questions',
            'position' => 1,
        ]);

        $response->assertRedirect(route('kb.categories'));
        $this->assertDatabaseHas('kb_categories', [
            'organization_id' => $this->org->id,
            'name' => 'FAQ',
        ]);
    }

    public function test_org_admin_can_view_articles(): void
    {
        $response = $this->actingAs($this->orgAdmin)->get($this->kbUrl('/articles'));

        $response->assertOk();
        $response->assertViewIs('kb.articles');
    }

    public function test_org_admin_can_create_article(): void
    {
        $response = $this->actingAs($this->orgAdmin)->post($this->kbUrl('/articles'), [
            'title' => 'How to reset password',
            'content' => 'Go to settings and click reset.',
            'kb_category_id' => $this->category->id,
            'priority' => 5,
            'visible_on_webchat' => '1',
        ]);

        $response->assertRedirect(route('kb.articles'));
        $this->assertDatabaseHas('kb_articles', [
            'organization_id' => $this->org->id,
            'title' => 'How to reset password',
        ]);
    }

    public function test_org_admin_can_view_article(): void
    {
        $article = $this->createArticle();

        $response = $this->actingAs($this->orgAdmin)->get($this->kbUrl("/articles/{$article->id}"));

        $response->assertOk();
        $response->assertViewHas('article');
    }

    public function test_org_admin_can_update_article(): void
    {
        $article = $this->createArticle();

        $response = $this->actingAs($this->orgAdmin)->post($this->kbUrl("/articles/{$article->id}/update"), [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
            'kb_category_id' => $this->category->id,
            'change_summary' => 'Fixed typo',
        ]);

        $response->assertRedirect(route('kb.articles.show', $article));
        $this->assertEquals('Updated Title', $article->fresh()->title);
        $this->assertDatabaseHas('kb_versions', [
            'kb_article_id' => $article->id,
            'change_summary' => 'Fixed typo',
        ]);
    }

    public function test_org_admin_can_publish_article(): void
    {
        $article = $this->createArticle();

        $response = $this->actingAs($this->orgAdmin)->post($this->kbUrl("/articles/{$article->id}/publish"));

        $response->assertRedirect(route('kb.articles.show', $article));
        $this->assertEquals('published', $article->fresh()->status);
    }

    public function test_org_admin_can_archive_article(): void
    {
        $article = $this->createArticle(['status' => 'published']);

        $response = $this->actingAs($this->orgAdmin)->post($this->kbUrl("/articles/{$article->id}/archive"));

        $response->assertRedirect(route('kb.articles.show', $article));
        $this->assertEquals('archived', $article->fresh()->status);
    }

    public function test_agent_cannot_create_article(): void
    {
        $response = $this->actingAs($this->agent)->post($this->kbUrl('/articles'), [
            'title' => 'Unauthorized',
            'content' => 'Should fail.',
            'kb_category_id' => $this->category->id,
        ]);

        $response->assertForbidden();
    }

    public function test_cross_tenant_isolation(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherArticle = KbArticle::create([
            'organization_id' => $otherOrg->id,
            'kb_category_id' => $this->category->id,
            'created_by' => $this->orgAdmin->id,
            'title' => 'Other Org Article',
            'content' => 'Should not be visible.',
            'status' => 'draft',
            'priority' => 0,
        ]);

        $response = $this->actingAs($this->orgAdmin)->get($this->kbUrl("/articles/{$otherArticle->id}"));

        $response->assertNotFound();
    }
}
