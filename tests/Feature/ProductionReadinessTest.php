<?php

namespace Tests\Feature;

use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([\Illuminate\Broadcasting\BroadcastEvent::class]);
    }

    public function test_health_check_app_responds(): void
    {
        $response = $this->get('http://app.chatme.test/health/app');
        $response->assertOk();
    }

    public function test_health_check_db_responds(): void
    {
        $response = $this->get('http://app.chatme.test/health/db');
        $response->assertOk();
    }

    public function test_security_headers_present(): void
    {
        $response = $this->get('http://app.chatme.test/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_csp_header_present(): void
    {
        $response = $this->get('http://app.chatme.test/');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function test_csp_does_not_include_unsafe_eval(): void
    {
        $csp = config('security.csp');
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }

    public function test_stripe_graceful_degradation(): void
    {
        $service = app(StripeService::class);
        $this->assertFalse($service->isConfigured());
    }

    public function test_deploy_command_exists(): void
    {
        $this->artisan('app:deploy', ['--seed' => true])
            ->assertSuccessful();
    }

    public function test_config_has_base_domain(): void
    {
        $this->assertNotNull(config('app.base_domain'));
    }

    public function test_stripe_config_exists(): void
    {
        $this->assertArrayHasKey('stripe', config('services'));
        $this->assertArrayHasKey('secret_key', config('services.stripe'));
        $this->assertArrayHasKey('webhook_secret', config('services.stripe'));
    }

    public function test_cors_config_has_stripe_header(): void
    {
        $headers = config('cors.allowed_headers');
        $this->assertContains('Stripe-Signature', $headers);
    }

    public function test_production_safety_middleware_disables_debug(): void
    {
        config(['app.debug' => true]);
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get('http://app.chatme.test/');

        $this->assertFalse(config('app.debug'));
    }
}
