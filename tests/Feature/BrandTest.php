<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Organization;
use App\Models\User;
use App\Services\VectorSearchService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([BroadcastEvent::class]);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::factory()->create();
        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('org_admin');

        $this->app->instance('tenant', $this->org);
    }

    public function test_brand_crud_index(): void
    {
        Brand::factory()->count(3)->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->admin)
            ->get('http://app.chatme.test/brands');

        $response->assertOk();
        $response->assertViewHas('brands');
    }

    public function test_brand_create(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('http://app.chatme.test/brands', [
                'name' => 'Rosanegra Polanco',
                'description' => 'Restaurant mediterráneo',
                'color' => '#e11d48',
                'ai_context' => 'Cocina mediterránea, Av. Masaryk 330',
            ]);

        $response->assertRedirect(route('brands.index'));

        $this->assertDatabaseHas('brands', [
            'organization_id' => $this->org->id,
            'name' => 'Rosanegra Polanco',
        ]);

        $brand = Brand::first();
        $this->assertEquals('Cocina mediterránea, Av. Masaryk 330', $brand->settings['ai_context']);
    }

    public function test_brand_update(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->admin)
            ->put("http://app.chatme.test/brands/{$brand->id}", [
                'name' => 'Updated Brand',
                'description' => 'Updated desc',
                'color' => '#16a34a',
                'ai_context' => 'New context',
            ]);

        $response->assertRedirect(route('brands.show', $brand));
        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name' => 'Updated Brand']);
    }

    public function test_brand_delete(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->admin)
            ->delete("http://app.chatme.test/brands/{$brand->id}");

        $response->assertRedirect(route('brands.index'));
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }

    public function test_brand_cannot_delete_with_conversations(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);
        $channel = Channel::factory()->create(['organization_id' => $this->org->id, 'brand_id' => $brand->id]);
        Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'brand_id' => $brand->id,
            'channel_id' => $channel->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("http://app.chatme.test/brands/{$brand->id}");

        $response->assertRedirect(route('brands.show', $brand));
        $this->assertDatabaseHas('brands', ['id' => $brand->id]);
    }

    public function test_brand_show(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);
        Channel::factory()->create(['organization_id' => $this->org->id, 'brand_id' => $brand->id]);

        $response = $this->actingAs($this->admin)
            ->get("http://app.chatme.test/brands/{$brand->id}");

        $response->assertOk();
        $response->assertSee($brand->name);
    }

    public function test_agent_can_view_brands(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        Brand::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($agent)
            ->get('http://app.chatme.test/brands');

        $response->assertOk();
    }

    public function test_agent_cannot_create_brand(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $response = $this->actingAs($agent)
            ->post('http://app.chatme.test/brands', ['name' => 'Test']);

        $response->assertForbidden();
    }

    public function test_inbox_filters_by_brand(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);
        $channel = Channel::factory()->create(['organization_id' => $this->org->id, 'brand_id' => $brand->id]);

        Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'brand_id' => $brand->id,
            'channel_id' => $channel->id,
        ]);
        Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'brand_id' => null,
            'channel_id' => Channel::factory()->create(['organization_id' => $this->org->id])->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("http://app.chatme.test/inbox?brand_id={$brand->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->viewData('conversations')->total());
    }

    public function test_kb_articles_filter_by_brand(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);
        $cat = KbCategory::factory()->create(['organization_id' => $this->org->id]);

        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'brand_id' => $brand->id,
            'kb_category_id' => $cat->id,
        ]);
        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'brand_id' => null,
            'kb_category_id' => $cat->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("http://app.chatme.test/kb/articles?brand_id={$brand->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->viewData('articles')->total());
    }

    public function test_kb_articles_filter_global(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);
        $cat = KbCategory::factory()->create(['organization_id' => $this->org->id]);

        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'brand_id' => $brand->id,
            'kb_category_id' => $cat->id,
        ]);
        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'brand_id' => null,
            'kb_category_id' => $cat->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('http://app.chatme.test/kb/articles?brand_id=global');

        $response->assertOk();
        $this->assertEquals(1, $response->viewData('articles')->total());
    }

    public function test_channel_assigned_to_brand(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->admin)
            ->post('http://app.chatme.test/settings/channels', [
                'name' => 'WA Rosanegra',
                'type' => 'whatsapp',
                'brand_id' => $brand->id,
                'phone_number_id' => '12345',
                'waba_id' => '67890',
                'access_token' => 'EAA' . str_repeat('x', 40),
                'verify_token' => str_repeat('y', 32),
                'app_secret' => str_repeat('z', 32),
                'display_phone' => '+525512345678',
            ]);

        $response->assertRedirect();

        $channel = Channel::where('name', 'WA Rosanegra')->first();
        $this->assertEquals($brand->id, $channel->brand_id);
    }

    public function test_conversation_inherits_brand_from_channel(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);
        $channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'brand_id' => $brand->id,
        ]);

        $conversation = Conversation::create([
            'organization_id' => $this->org->id,
            'brand_id' => $channel->brand_id,
            'channel_id' => $channel->id,
            'contact_name' => 'Test User',
            'contact_identifier' => '5551234567',
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => now(),
        ]);

        $this->assertEquals($brand->id, $conversation->brand_id);
        $this->assertEquals($brand->id, $conversation->brand->id);
    }

    public function test_vector_search_cross_brand_fallback(): void
    {
        $brandA = Brand::factory()->create(['organization_id' => $this->org->id, 'name' => 'Rosanegra']);
        $brandB = Brand::factory()->create(['organization_id' => $this->org->id, 'name' => 'Sabor de Mar']);
        $cat = KbCategory::factory()->create(['organization_id' => $this->org->id]);

        // Brand A article
        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'brand_id' => $brandA->id,
            'kb_category_id' => $cat->id,
            'title' => 'Terraza Rosanegra',
            'content' => 'Rosanegra tiene terraza en el segundo piso.',
            'status' => 'published',
        ]);

        // Brand B article
        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'brand_id' => $brandB->id,
            'kb_category_id' => $cat->id,
            'title' => 'Menu Sabor de Mar',
            'content' => 'Especialidad en mariscos frescos.',
            'status' => 'published',
        ]);

        // Global article
        KbArticle::factory()->create([
            'organization_id' => $this->org->id,
            'brand_id' => null,
            'kb_category_id' => $cat->id,
            'title' => 'Grupo Rosanegra Info',
            'content' => 'Grupo con restaurantes Rosanegra y Sabor de Mar.',
            'status' => 'published',
        ]);

        $service = app(VectorSearchService::class);

        // Search from Brand A — should find all (cross-brand)
        $results = $service->search($this->org, 'terraza', 10, null, $brandA->id);
        $this->assertGreaterThanOrEqual(1, $results->count());

        // The Brand A article about terraza should be first or present
        $this->assertTrue($results->contains(fn ($a) => str_contains($a->title, 'Terraza')));
    }

    public function test_brand_ai_context_stored(): void
    {
        $brand = Brand::factory()->withAiContext('Restaurante de mariscos en CDMX')
            ->create(['organization_id' => $this->org->id]);

        $this->assertEquals('Restaurante de mariscos en CDMX', $brand->getAiContext());
    }

    public function test_brand_model_relationships(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);
        $channel = Channel::factory()->create(['organization_id' => $this->org->id, 'brand_id' => $brand->id]);
        KbCategory::factory()->create(['organization_id' => $this->org->id, 'brand_id' => $brand->id]);

        $this->assertEquals(1, $brand->channels()->count());
        $this->assertEquals(1, $brand->kbCategories()->count());
        $this->assertEquals($this->org->id, $brand->organization->id);
    }
}
