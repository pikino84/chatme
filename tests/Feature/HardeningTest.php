<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureProductionSafety;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    // ── Reverb Origins ──

    public function test_reverb_allowed_origins_defaults_to_wildcard(): void
    {
        $origins = config('reverb.apps.apps.0.allowed_origins');

        $this->assertIsArray($origins);
        $this->assertEquals(['*'], $origins);
    }

    // ── Session Security ──

    public function test_session_http_only_is_true(): void
    {
        $this->assertTrue(config('session.http_only'));
    }

    public function test_session_same_site_is_lax(): void
    {
        $this->assertEquals('lax', config('session.same_site'));
    }

    public function test_session_secure_cookie_is_configurable(): void
    {
        $secure = config('session.secure');
        $this->assertTrue(
            is_null($secure) || is_bool($secure),
            'session.secure must be null or boolean'
        );
    }

    // ── Production Safety Middleware ──

    public function test_production_safety_middleware_logs_when_debug_in_production(): void
    {
        Log::shouldReceive('critical')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'APP_DEBUG is enabled in production');
            });

        // Simulate production + debug=true
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);

        $middleware = new EnsureProductionSafety();
        $middleware->handle(Request::create('/'), fn ($r) => response('ok'));
    }

    public function test_production_safety_middleware_does_not_log_in_local(): void
    {
        Log::shouldReceive('critical')->never();

        app()->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);

        $middleware = new EnsureProductionSafety();
        $middleware->handle(Request::create('/'), fn ($r) => response('ok'));
    }

    public function test_production_safety_middleware_does_not_log_when_debug_false(): void
    {
        Log::shouldReceive('critical')->never();

        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $middleware = new EnsureProductionSafety();
        $middleware->handle(Request::create('/'), fn ($r) => response('ok'));
    }

    // ── Queue config for Horizon ──

    public function test_horizon_config_has_critical_and_low_supervisors(): void
    {
        $defaults = config('horizon.defaults');

        $this->assertArrayHasKey('supervisor-critical', $defaults);
        $this->assertArrayHasKey('supervisor-low', $defaults);
        $this->assertContains('critical', $defaults['supervisor-critical']['queue']);
        $this->assertContains('default', $defaults['supervisor-critical']['queue']);
        $this->assertContains('low', $defaults['supervisor-low']['queue']);
    }
}
