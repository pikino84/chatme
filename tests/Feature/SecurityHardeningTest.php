<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([\Illuminate\Broadcasting\BroadcastEvent::class]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansAndFeaturesSeeder::class);

        $this->org = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('org_admin');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        app()->instance('tenant', $this->org);
    }

    // --- Security Headers ---

    public function test_security_headers_are_present(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_csp_header_contains_expected_directives(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src", $csp);
        $this->assertStringContainsString("style-src", $csp);
    }

    // --- Audit Logging ---

    public function test_audit_service_logs_action(): void
    {
        $service = app(AuditService::class);

        $log = $service->log(
            action: 'test.action',
            organizationId: $this->org->id,
            userId: $this->admin->id,
            entityType: 'User',
            entityId: $this->agent->id,
            newValues: ['role' => 'supervisor'],
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test.action',
            'organization_id' => $this->org->id,
            'user_id' => $this->admin->id,
            'entity_type' => 'User',
            'entity_id' => $this->agent->id,
        ]);
    }

    public function test_audit_service_respects_disabled_config(): void
    {
        config(['security.audit_enabled' => false]);

        $service = app(AuditService::class);
        $log = $service->log(action: 'should.not.save', userId: $this->admin->id);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'should.not.save']);
    }

    public function test_role_change_creates_audit_log(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('settings.team.role', $this->agent), [
                'role' => 'supervisor',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.role_changed',
            'entity_type' => 'User',
            'entity_id' => $this->agent->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_user_toggle_creates_audit_log(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('settings.team.toggle', $this->agent));

        $response->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.deactivated',
            'entity_type' => 'User',
            'entity_id' => $this->agent->id,
        ]);
    }

    public function test_login_creates_audit_log(): void
    {
        // Re-enable login event listeners (Event::fake blocks them)
        Event::fakeExcept([
            \Illuminate\Auth\Events\Login::class,
        ]);

        $user = User::factory()->create([
            'organization_id' => $this->org->id,
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login',
            'user_id' => $user->id,
        ]);
    }

    // --- XSS Prevention ---

    public function test_message_body_is_sanitized(): void
    {
        $conversation = \App\Models\Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => \App\Models\Channel::factory()->create([
                'organization_id' => $this->org->id,
            ])->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('inbox.conversations.messages.store', $conversation), [
                'body' => '<script>alert("xss")</script>Hello',
                'type' => 'text',
            ]);

        $message = \App\Models\Message::where('conversation_id', $conversation->id)->first();
        $this->assertNotNull($message);
        $this->assertStringNotContainsString('<script>', $message->body);
        $this->assertStringContainsString('Hello', $message->body);
    }

    // --- Rate Limiting ---

    public function test_tenant_api_rate_limiter_is_configured(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\RateLimiter::limiter('tenant-api') !== null
        );
    }

    // --- CORS Config ---

    public function test_cors_config_is_published(): void
    {
        $cors = config('cors');
        $this->assertIsArray($cors);
        $this->assertArrayHasKey('allowed_origins', $cors);
        $this->assertContains('X-Webchat-Token', $cors['allowed_headers']);
    }
}
