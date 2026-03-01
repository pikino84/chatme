<?php

namespace Tests\Feature;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\KbVersion;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbArticleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->author = User::factory()->create(['organization_id' => $this->org->id]);
    }

    // --- Model Relations ---

    public function test_article_belongs_to_organization(): void
    {
        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertEquals($this->org->id, $article->organization_id);
    }

    public function test_article_belongs_to_category(): void
    {
        $category = KbCategory::factory()->create(['organization_id' => $this->org->id]);
        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'kb_category_id' => $category->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertEquals($category->id, $article->category->id);
    }

    public function test_article_can_exist_without_category(): void
    {
        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'kb_category_id' => null,
            'created_by' => $this->author->id,
        ]);

        $this->assertNull($article->category);
    }

    public function test_article_has_creator(): void
    {
        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertEquals($this->author->id, $article->creator->id);
    }

    public function test_article_has_updater(): void
    {
        $updater = User::factory()->create(['organization_id' => $this->org->id]);
        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
            'updated_by' => $updater->id,
        ]);

        $this->assertEquals($updater->id, $article->updater->id);
    }

    public function test_article_has_versions(): void
    {
        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);
        KbVersion::factory()->create([
            'organization_id' => $this->org->id,
            'kb_article_id' => $article->id,
            'changed_by' => $this->author->id,
        ]);

        $this->assertCount(1, $article->versions);
    }

    // --- Status Helpers ---

    public function test_article_status_helpers(): void
    {
        $draft = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
            'status' => 'draft',
        ]);
        $published = KbArticle::factory()->published()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);
        $archived = KbArticle::factory()->archived()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isPublished());

        $this->assertTrue($published->isPublished());
        $this->assertFalse($published->isDraft());

        $this->assertTrue($archived->isArchived());
        $this->assertFalse($archived->isPublished());
    }

    // --- Visibility Helpers ---

    public function test_article_visibility_helpers(): void
    {
        $article = KbArticle::factory()->visibleOnWebchat()->visibleOnWhatsApp()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertTrue($article->isVisibleOn('webchat'));
        $this->assertTrue($article->isVisibleOn('whatsapp'));
        $this->assertFalse($article->isVisibleOn('instagram'));
        $this->assertFalse($article->isVisibleOn('facebook'));
        $this->assertFalse($article->isVisibleOn('unknown'));
    }

    // --- Tenant Scope ---

    public function test_article_tenant_scope(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);

        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);
        KbArticle::factory()->create([
            'organization_id' => $otherOrg->id,
            'created_by' => $otherUser->id,
        ]);

        app()->instance('tenant', $this->org);
        $this->assertCount(1, KbArticle::all());
    }

    // --- Policy ---

    public function test_policy_org_admin_can_view_article(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertTrue($admin->can('view', $article));
    }

    public function test_policy_agent_can_view_article(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertTrue($agent->can('view', $article));
    }

    public function test_policy_org_admin_can_create_article(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $this->assertTrue($admin->can('create', KbArticle::class));
    }

    public function test_policy_agent_cannot_create_article(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->assertFalse($agent->can('create', KbArticle::class));
    }

    public function test_policy_org_admin_can_delete_article(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertTrue($admin->can('delete', $article));
    }

    public function test_policy_agent_cannot_delete_article(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertFalse($agent->can('delete', $article));
    }

    public function test_policy_org_admin_can_publish_article(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertTrue($admin->can('publish', $article));
    }

    public function test_policy_supervisor_cannot_publish_article(): void
    {
        $supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $supervisor->assignRole('supervisor');

        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertFalse($supervisor->can('publish', $article));
    }

    public function test_policy_cross_tenant_cannot_view(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $otherUser->assignRole('org_admin');

        $article = KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertFalse($otherUser->can('view', $article));
    }

    // --- Organization Relations ---

    public function test_organization_has_kb_articles(): void
    {
        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->author->id,
        ]);

        $this->assertCount(1, $this->org->kbArticles);
    }

    public function test_organization_has_kb_categories(): void
    {
        KbCategory::factory()->create(['organization_id' => $this->org->id]);

        $this->assertCount(1, $this->org->kbCategories);
    }
}
