<?php

namespace Tests\Feature;

use App\Exceptions\BillingLimitException;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Plan;
use App\Models\User;
use App\Services\ContactService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $otherOrg;
    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([\Illuminate\Broadcasting\BroadcastEvent::class]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansAndFeaturesSeeder::class);

        $this->org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org', 'status' => 'active']);
        $this->otherOrg = Organization::create(['name' => 'Other Org', 'slug' => 'other-org', 'status' => 'active']);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('org_admin');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        // Subscribe to Professional plan
        $plan = Plan::where('slug', 'professional')->first();
        OrganizationSubscription::create([
            'organization_id' => $this->org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        app()->instance('tenant', $this->org);
    }

    // --- Controller / UI Tests ---

    public function test_admin_can_view_contacts_index(): void
    {
        Contact::factory()->count(3)->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->admin)->get(route('contacts.index'));

        $response->assertOk();
        $response->assertViewHas('contacts');
    }

    public function test_admin_can_search_contacts(): void
    {
        Contact::factory()->create(['organization_id' => $this->org->id, 'name' => 'Juan Perez']);
        Contact::factory()->create(['organization_id' => $this->org->id, 'name' => 'Maria Lopez']);

        $response = $this->actingAs($this->admin)->get(route('contacts.index', ['search' => 'Juan']));

        $response->assertOk();
        $response->assertSee('Juan Perez');
        $response->assertDontSee('Maria Lopez');
    }

    public function test_admin_can_create_contact(): void
    {
        $response = $this->actingAs($this->admin)->post(route('contacts.store'), [
            'name' => 'Nuevo Contacto',
            'email' => 'nuevo@test.com',
            'phone' => '+525551234567',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contacts', [
            'organization_id' => $this->org->id,
            'name' => 'Nuevo Contacto',
            'email' => 'nuevo@test.com',
        ]);
    }

    public function test_admin_can_update_contact(): void
    {
        $contact = Contact::factory()->create(['organization_id' => $this->org->id, 'name' => 'Original']);

        $response = $this->actingAs($this->admin)->put(route('contacts.update', $contact), [
            'name' => 'Actualizado',
            'email' => 'updated@test.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => 'Actualizado']);
    }

    public function test_admin_can_delete_contact(): void
    {
        $contact = Contact::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->admin)->delete(route('contacts.destroy', $contact));

        $response->assertRedirect(route('contacts.index'));
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_admin_can_view_contact_show(): void
    {
        $contact = Contact::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->admin)->get(route('contacts.show', $contact));

        $response->assertOk();
        $response->assertSee($contact->name);
    }

    public function test_agent_can_view_contacts(): void
    {
        $response = $this->actingAs($this->agent)->get(route('contacts.index'));
        $response->assertOk();
    }

    public function test_agent_cannot_delete_contacts(): void
    {
        $contact = Contact::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->agent)->delete(route('contacts.destroy', $contact));
        $response->assertForbidden();
    }

    // --- Tenant Isolation ---

    public function test_cannot_view_contact_from_other_org(): void
    {
        $otherContact = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->actingAs($this->admin)->get(route('contacts.show', $otherContact));
        // Global scope filters out the contact, so it returns 404 (not found) instead of 403
        $response->assertNotFound();
    }

    // --- Service Tests ---

    public function test_contact_service_find_or_create_by_phone(): void
    {
        $service = app(ContactService::class);

        $contact1 = $service->findOrCreateByPhone($this->org->id, '+525551111111', ['name' => 'Test']);
        $contact2 = $service->findOrCreateByPhone($this->org->id, '+525551111111', ['name' => 'Duplicate']);

        $this->assertEquals($contact1->id, $contact2->id);
        $this->assertEquals(1, Contact::where('phone', '+525551111111')->count());
    }

    public function test_contact_service_merge(): void
    {
        $service = app(ContactService::class);

        $primary = Contact::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Primary',
            'email' => 'primary@test.com',
            'company' => null,
        ]);
        $secondary = Contact::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Secondary',
            'company' => 'Merged Company',
        ]);

        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);
        $conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $channel->id,
            'contact_id' => $secondary->id,
        ]);

        $merged = $service->merge($primary, $secondary);

        $this->assertEquals('Primary', $merged->name);
        $this->assertEquals('Merged Company', $merged->company);
        $this->assertDatabaseMissing('contacts', ['id' => $secondary->id]);
        $this->assertEquals($primary->id, $conversation->fresh()->contact_id);
    }

    public function test_contact_service_cannot_merge_cross_tenant(): void
    {
        $service = app(ContactService::class);

        $contact1 = Contact::factory()->create(['organization_id' => $this->org->id]);
        $contact2 = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->expectException(\InvalidArgumentException::class);
        $service->merge($contact1, $contact2);
    }

    // --- CSV Import ---

    public function test_csv_import_works(): void
    {
        $csv = "name,email,phone,company\nJuan,juan@test.com,+525551111111,Acme\nMaria,maria@test.com,+525552222222,Corp";
        $service = app(ContactService::class);

        $result = $service->importCsv($this->org->id, $csv);

        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertDatabaseHas('contacts', ['name' => 'Juan', 'organization_id' => $this->org->id]);
    }

    public function test_csv_import_skips_duplicates(): void
    {
        Contact::factory()->create(['organization_id' => $this->org->id, 'phone' => '+525551111111']);

        $csv = "name,phone\nDuplicate,+525551111111\nNew,+525553333333";
        $service = app(ContactService::class);

        $result = $service->importCsv($this->org->id, $csv);

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(1, $result['skipped']);
    }

    public function test_csv_import_requires_name_column(): void
    {
        $csv = "email,phone\njuan@test.com,+525551111111";
        $service = app(ContactService::class);

        $result = $service->importCsv($this->org->id, $csv);

        $this->assertEquals(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_csv_import_via_controller(): void
    {
        $csv = "name,email\nTest User,test@example.com";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('contacts.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('contacts.index'));
        $this->assertDatabaseHas('contacts', ['name' => 'Test User', 'organization_id' => $this->org->id]);
    }

    // --- Billing Limit ---

    public function test_billing_limit_blocks_contact_creation(): void
    {
        // Switch to Starter plan (max_contacts = 100)
        $starterPlan = Plan::where('slug', 'starter')->first();
        OrganizationSubscription::where('organization_id', $this->org->id)->update(['plan_id' => $starterPlan->id]);

        // Create 100 contacts to hit the limit
        Contact::factory()->count(100)->create(['organization_id' => $this->org->id]);

        $service = app(ContactService::class);
        $this->expectException(BillingLimitException::class);
        $service->create($this->org->id, ['name' => 'Over Limit']);
    }
}
