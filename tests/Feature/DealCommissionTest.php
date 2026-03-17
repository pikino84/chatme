<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DealCommission;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $admin;
    protected User $agent;
    protected User $supervisor;
    protected Pipeline $pipeline;
    protected PipelineStage $stage;
    protected Deal $deal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansAndFeaturesSeeder::class);

        $this->org = Organization::factory()->create();
        app()->instance('tenant', $this->org);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('org_admin');

        $this->supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $this->supervisor->assignRole('supervisor');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        $plan = \App\Models\Plan::where('slug', 'enterprise')->first();
        (new BillingService())->subscribe($this->org, $plan);

        $this->pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id, 'is_default' => true]);
        $this->stage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
        ]);
        $this->deal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
            'value' => 10000.00,
            'currency' => 'MXN',
        ]);
    }

    // ── Create Commission ──

    public function test_admin_can_add_commission(): void
    {
        $response = $this->actingAs($this->admin)->post(route('deals.commissions.store', $this->deal), [
            'user_id' => $this->agent->id,
            'percentage' => 10,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('deal_commissions', [
            'deal_id' => $this->deal->id,
            'user_id' => $this->agent->id,
            'percentage' => '10.00',
            'amount' => '1000.00', // 10% of 10000
            'status' => 'pending',
        ]);
    }

    public function test_commission_amount_calculated_correctly(): void
    {
        $this->actingAs($this->admin)->post(route('deals.commissions.store', $this->deal), [
            'user_id' => $this->agent->id,
            'percentage' => 5.5,
        ]);

        $commission = DealCommission::where('deal_id', $this->deal->id)->first();
        $this->assertEquals('550.00', $commission->amount);
    }

    public function test_supervisor_cannot_add_commission(): void
    {
        $response = $this->actingAs($this->supervisor)->post(route('deals.commissions.store', $this->deal), [
            'user_id' => $this->agent->id,
            'percentage' => 10,
        ]);

        $response->assertForbidden();
    }

    public function test_agent_cannot_add_commission(): void
    {
        $response = $this->actingAs($this->agent)->post(route('deals.commissions.store', $this->deal), [
            'user_id' => $this->agent->id,
            'percentage' => 10,
        ]);

        $response->assertForbidden();
    }

    public function test_commission_requires_user_and_percentage(): void
    {
        $response = $this->actingAs($this->admin)->post(route('deals.commissions.store', $this->deal), []);

        $response->assertSessionHasErrors(['user_id', 'percentage']);
    }

    public function test_percentage_must_be_between_0_and_100(): void
    {
        $response = $this->actingAs($this->admin)->post(route('deals.commissions.store', $this->deal), [
            'user_id' => $this->agent->id,
            'percentage' => 150,
        ]);

        $response->assertSessionHasErrors('percentage');
    }

    public function test_cannot_add_commission_for_other_org_user(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);

        $response = $this->actingAs($this->admin)->post(route('deals.commissions.store', $this->deal), [
            'user_id' => $otherUser->id,
            'percentage' => 10,
        ]);

        $response->assertForbidden();
    }

    // ── Update Status ──

    public function test_admin_can_update_commission_status(): void
    {
        $commission = DealCommission::create([
            'organization_id' => $this->org->id,
            'deal_id' => $this->deal->id,
            'user_id' => $this->agent->id,
            'percentage' => 10,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->post(route('deals.commissions.status', [$this->deal, $commission]), [
            'status' => 'approved',
        ]);

        $response->assertRedirect();
        $this->assertEquals('approved', $commission->fresh()->status);
    }

    public function test_can_transition_through_all_statuses(): void
    {
        $commission = DealCommission::create([
            'organization_id' => $this->org->id,
            'deal_id' => $this->deal->id,
            'user_id' => $this->agent->id,
            'percentage' => 10,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        // pending -> approved
        $this->actingAs($this->admin)->post(route('deals.commissions.status', [$this->deal, $commission]), ['status' => 'approved']);
        $this->assertEquals('approved', $commission->fresh()->status);

        // approved -> paid
        $this->actingAs($this->admin)->post(route('deals.commissions.status', [$this->deal, $commission]), ['status' => 'paid']);
        $this->assertEquals('paid', $commission->fresh()->status);
    }

    public function test_can_cancel_commission(): void
    {
        $commission = DealCommission::create([
            'organization_id' => $this->org->id,
            'deal_id' => $this->deal->id,
            'user_id' => $this->agent->id,
            'percentage' => 10,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)->post(route('deals.commissions.status', [$this->deal, $commission]), ['status' => 'cancelled']);
        $this->assertEquals('cancelled', $commission->fresh()->status);
    }

    public function test_invalid_status_rejected(): void
    {
        $commission = DealCommission::create([
            'organization_id' => $this->org->id,
            'deal_id' => $this->deal->id,
            'user_id' => $this->agent->id,
            'percentage' => 10,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->post(route('deals.commissions.status', [$this->deal, $commission]), [
            'status' => 'invalid_status',
        ]);

        $response->assertSessionHasErrors('status');
    }

    // ── Delete ──

    public function test_admin_can_delete_commission(): void
    {
        $commission = DealCommission::create([
            'organization_id' => $this->org->id,
            'deal_id' => $this->deal->id,
            'user_id' => $this->agent->id,
            'percentage' => 10,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('deals.commissions.destroy', [$this->deal, $commission]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('deal_commissions', ['id' => $commission->id]);
    }

    public function test_cannot_delete_commission_from_other_deal(): void
    {
        $otherDeal = Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stage->id,
        ]);

        $commission = DealCommission::create([
            'organization_id' => $this->org->id,
            'deal_id' => $otherDeal->id,
            'user_id' => $this->agent->id,
            'percentage' => 10,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('deals.commissions.destroy', [$this->deal, $commission]));

        $response->assertNotFound();
    }

    // ── Deal Drawer Integration ──

    public function test_deal_show_includes_commissions(): void
    {
        DealCommission::create([
            'organization_id' => $this->org->id,
            'deal_id' => $this->deal->id,
            'user_id' => $this->agent->id,
            'percentage' => 15,
            'amount' => 1500,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->get(route('deals.show', $this->deal));

        $response->assertOk();
        $response->assertSee('Comisiones');
        $response->assertSee($this->agent->name);
        $response->assertSee('15');
    }

    // ── Tenant Isolation ──

    public function test_cannot_add_commission_to_other_org_deal(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherPipeline = Pipeline::factory()->create(['organization_id' => $otherOrg->id]);
        $otherStage = PipelineStage::factory()->create([
            'organization_id' => $otherOrg->id,
            'pipeline_id' => $otherPipeline->id,
        ]);
        $otherDeal = Deal::factory()->create([
            'organization_id' => $otherOrg->id,
            'pipeline_id' => $otherPipeline->id,
            'pipeline_stage_id' => $otherStage->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('deals.commissions.store', $otherDeal), [
            'user_id' => $this->agent->id,
            'percentage' => 10,
        ]);

        // Global scope filters out the deal, so it returns 404
        $response->assertNotFound();
    }
}
