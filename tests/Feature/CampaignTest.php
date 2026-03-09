<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Services\CampaignService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $agent;
    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([\Illuminate\Broadcasting\BroadcastEvent::class]);
        Queue::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansAndFeaturesSeeder::class);

        $this->org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org', 'status' => 'active']);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('org_admin');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        $this->channel = Channel::factory()->create(['organization_id' => $this->org->id, 'type' => 'whatsapp']);

        // Subscribe to Professional plan (campaigns_enabled = true)
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

    public function test_admin_can_view_campaigns(): void
    {
        $response = $this->actingAs($this->admin)->get(route('campaigns.index'));
        $response->assertOk();
    }

    public function test_feature_gating_blocks_starter_plan(): void
    {
        $starterPlan = Plan::where('slug', 'starter')->first();
        OrganizationSubscription::where('organization_id', $this->org->id)->update(['plan_id' => $starterPlan->id]);

        $response = $this->actingAs($this->admin)->get(route('campaigns.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_create_campaign(): void
    {
        $response = $this->actingAs($this->admin)->post(route('campaigns.store'), [
            'name' => 'Test Campaign',
            'channel_id' => $this->channel->id,
            'message_template' => 'Hello!',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('campaigns', [
            'organization_id' => $this->org->id,
            'name' => 'Test Campaign',
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_add_recipients(): void
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'created_by' => $this->admin->id,
        ]);

        $contacts = Contact::factory()->count(3)->create([
            'organization_id' => $this->org->id,
        ]);

        $service = app(CampaignService::class);
        $added = $service->addRecipients($campaign, $contacts->pluck('id')->toArray());

        $this->assertEquals(3, $added);
        $this->assertEquals(3, $campaign->fresh()->total_recipients);
    }

    public function test_duplicate_recipients_are_not_added(): void
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'created_by' => $this->admin->id,
        ]);

        $contact = Contact::factory()->create(['organization_id' => $this->org->id]);

        $service = app(CampaignService::class);
        $service->addRecipients($campaign, [$contact->id]);
        $added = $service->addRecipients($campaign, [$contact->id]);

        $this->assertEquals(0, $added);
        $this->assertEquals(1, $campaign->fresh()->total_recipients);
    }

    public function test_campaign_can_be_sent(): void
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'created_by' => $this->admin->id,
            'total_recipients' => 1,
        ]);

        CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => Contact::factory()->create(['organization_id' => $this->org->id])->id,
        ]);

        $service = app(CampaignService::class);
        $result = $service->send($campaign);

        $this->assertEquals('running', $result->status);
        Queue::assertPushed(\App\Jobs\SendCampaignMessage::class);
    }

    public function test_campaign_can_be_canceled(): void
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'created_by' => $this->admin->id,
        ]);

        $service = app(CampaignService::class);
        $result = $service->cancel($campaign);

        $this->assertEquals('canceled', $result->status);
    }

    public function test_mark_recipient_sent_updates_stats(): void
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'created_by' => $this->admin->id,
            'status' => 'running',
            'total_recipients' => 1,
        ]);

        $recipient = CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => Contact::factory()->create(['organization_id' => $this->org->id])->id,
        ]);

        $service = app(CampaignService::class);
        $service->markRecipientSent($recipient);

        $campaign->refresh();
        $this->assertEquals(1, $campaign->sent_count);
        $this->assertEquals('completed', $campaign->status);
    }

    public function test_cannot_edit_running_campaign(): void
    {
        $campaign = Campaign::factory()->running()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'created_by' => $this->admin->id,
        ]);

        $service = app(CampaignService::class);

        $this->expectException(\LogicException::class);
        $service->update($campaign, ['name' => 'Updated']);
    }

    public function test_agent_cannot_create_campaign(): void
    {
        $response = $this->actingAs($this->agent)->post(route('campaigns.store'), [
            'name' => 'Test',
            'channel_id' => $this->channel->id,
            'message_template' => 'Hello',
        ]);

        $response->assertForbidden();
    }

    public function test_tenant_isolation(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $otherChannel = Channel::factory()->create(['organization_id' => $otherOrg->id]);
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $otherCampaign = Campaign::factory()->create([
            'organization_id' => $otherOrg->id,
            'channel_id' => $otherChannel->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('campaigns.show', $otherCampaign));
        $response->assertNotFound();
    }
}
