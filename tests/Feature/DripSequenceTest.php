<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\DripEnrollment;
use App\Models\DripSequence;
use App\Models\DripSequenceStep;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Services\DripService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DripSequenceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $agent;

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

        // Subscribe to Professional plan (drip_sequences_enabled = true)
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

    public function test_admin_can_view_drip_sequences(): void
    {
        $response = $this->actingAs($this->admin)->get(route('drip.index'));
        $response->assertOk();
    }

    public function test_feature_gating_blocks_starter_plan(): void
    {
        $starterPlan = Plan::where('slug', 'starter')->first();
        OrganizationSubscription::where('organization_id', $this->org->id)->update(['plan_id' => $starterPlan->id]);

        $response = $this->actingAs($this->admin)->get(route('drip.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_create_sequence(): void
    {
        $response = $this->actingAs($this->admin)->post(route('drip.store'), [
            'name' => 'Welcome Series',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('drip_sequences', [
            'organization_id' => $this->org->id,
            'name' => 'Welcome Series',
            'status' => 'paused',
        ]);
    }

    public function test_admin_can_add_step(): void
    {
        $sequence = DripSequence::factory()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($this->admin)->post(route('drip.steps.add', $sequence), [
            'delay_minutes' => 60,
            'message_template' => 'Hello!',
            'channel_type' => 'whatsapp',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('drip_sequence_steps', [
            'drip_sequence_id' => $sequence->id,
            'position' => 1,
            'delay_minutes' => 60,
        ]);
    }

    public function test_activate_requires_steps(): void
    {
        $sequence = DripSequence::factory()->create(['organization_id' => $this->org->id]);

        $service = app(DripService::class);

        $this->expectException(\LogicException::class);
        $service->activate($sequence);
    }

    public function test_activate_with_steps_succeeds(): void
    {
        $sequence = DripSequence::factory()->create(['organization_id' => $this->org->id]);
        DripSequenceStep::factory()->create(['drip_sequence_id' => $sequence->id]);

        $service = app(DripService::class);
        $result = $service->activate($sequence);

        $this->assertEquals('active', $result->status);
    }

    public function test_enroll_contact(): void
    {
        $sequence = DripSequence::factory()->active()->create(['organization_id' => $this->org->id]);
        DripSequenceStep::factory()->create([
            'drip_sequence_id' => $sequence->id,
            'delay_minutes' => 60,
        ]);

        $contact = Contact::factory()->create(['organization_id' => $this->org->id]);

        $service = app(DripService::class);
        $enrollment = $service->enroll($sequence, $contact, $this->org->id);

        $this->assertEquals('active', $enrollment->status);
        $this->assertEquals(1, $enrollment->current_step);
        $this->assertNotNull($enrollment->next_step_at);
    }

    public function test_cannot_enroll_twice(): void
    {
        $sequence = DripSequence::factory()->active()->create(['organization_id' => $this->org->id]);
        DripSequenceStep::factory()->create(['drip_sequence_id' => $sequence->id]);

        $contact = Contact::factory()->create(['organization_id' => $this->org->id]);

        $service = app(DripService::class);
        $service->enroll($sequence, $contact, $this->org->id);

        $this->expectException(\LogicException::class);
        $service->enroll($sequence, $contact, $this->org->id);
    }

    public function test_process_next_step_advances(): void
    {
        $sequence = DripSequence::factory()->active()->create(['organization_id' => $this->org->id]);
        DripSequenceStep::factory()->create([
            'drip_sequence_id' => $sequence->id,
            'position' => 1,
            'delay_minutes' => 60,
        ]);
        DripSequenceStep::factory()->create([
            'drip_sequence_id' => $sequence->id,
            'position' => 2,
            'delay_minutes' => 1440,
        ]);

        $contact = Contact::factory()->create(['organization_id' => $this->org->id, 'phone' => '+5215512345678']);

        $enrollment = DripEnrollment::create([
            'drip_sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'organization_id' => $this->org->id,
            'current_step' => 1,
            'status' => 'active',
            'next_step_at' => now()->subMinute(),
        ]);

        $service = app(DripService::class);
        $service->processNextStep($enrollment);

        $enrollment->refresh();
        $this->assertEquals(2, $enrollment->current_step);
        $this->assertEquals('active', $enrollment->status);
    }

    public function test_process_last_step_completes(): void
    {
        $sequence = DripSequence::factory()->active()->create(['organization_id' => $this->org->id]);
        DripSequenceStep::factory()->create([
            'drip_sequence_id' => $sequence->id,
            'position' => 1,
            'delay_minutes' => 60,
        ]);

        $contact = Contact::factory()->create(['organization_id' => $this->org->id, 'phone' => '+5215512345678']);

        $enrollment = DripEnrollment::create([
            'drip_sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'organization_id' => $this->org->id,
            'current_step' => 1,
            'status' => 'active',
            'next_step_at' => now()->subMinute(),
        ]);

        $service = app(DripService::class);
        $service->processNextStep($enrollment);

        $enrollment->refresh();
        $this->assertEquals('completed', $enrollment->status);
        $this->assertNull($enrollment->next_step_at);
    }

    public function test_cancel_enrollment(): void
    {
        $sequence = DripSequence::factory()->active()->create(['organization_id' => $this->org->id]);
        DripSequenceStep::factory()->create(['drip_sequence_id' => $sequence->id]);

        $contact = Contact::factory()->create(['organization_id' => $this->org->id]);

        $service = app(DripService::class);
        $enrollment = $service->enroll($sequence, $contact, $this->org->id);
        $service->cancelEnrollment($enrollment);

        $enrollment->refresh();
        $this->assertEquals('canceled', $enrollment->status);
        $this->assertNull($enrollment->next_step_at);
    }

    public function test_remove_step_reorders(): void
    {
        $sequence = DripSequence::factory()->create(['organization_id' => $this->org->id]);

        $service = app(DripService::class);
        $service->addStep($sequence, ['delay_minutes' => 60, 'message_template' => 'Step 1', 'channel_type' => 'whatsapp']);
        $step2 = $service->addStep($sequence, ['delay_minutes' => 120, 'message_template' => 'Step 2', 'channel_type' => 'whatsapp']);
        $service->addStep($sequence, ['delay_minutes' => 180, 'message_template' => 'Step 3', 'channel_type' => 'whatsapp']);

        $service->removeStep($step2);

        $steps = $sequence->steps()->orderBy('position')->get();
        $this->assertCount(2, $steps);
        $this->assertEquals(1, $steps[0]->position);
        $this->assertEquals(2, $steps[1]->position);
        $this->assertEquals('Step 3', $steps[1]->message_template);
    }

    public function test_agent_cannot_create_sequence(): void
    {
        $response = $this->actingAs($this->agent)->post(route('drip.store'), [
            'name' => 'Test',
        ]);

        $response->assertForbidden();
    }

    public function test_tenant_isolation(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $otherSequence = DripSequence::factory()->create(['organization_id' => $otherOrg->id]);

        $response = $this->actingAs($this->admin)->get(route('drip.show', $otherSequence));
        $response->assertNotFound();
    }

    public function test_admin_can_delete_sequence(): void
    {
        $sequence = DripSequence::factory()->create(['organization_id' => $this->org->id]);
        DripSequenceStep::factory()->create(['drip_sequence_id' => $sequence->id]);

        $response = $this->actingAs($this->admin)->delete(route('drip.destroy', $sequence));

        $response->assertRedirect(route('drip.index'));
        $this->assertDatabaseMissing('drip_sequences', ['id' => $sequence->id]);
        $this->assertDatabaseMissing('drip_sequence_steps', ['drip_sequence_id' => $sequence->id]);
    }
}
