<?php

namespace Akhtar\LaravelDebugTracer\Tests\Unit;

use Akhtar\LaravelDebugTracer\Tests\TestCase;

class DebugTracerPathExclusionTest extends TestCase
{
    public function test_it_skips_debug_dashboard_and_session_routes(): void
    {
        $this->assertTrue(debug_tracer_should_skip_http_tracing('debug-dashboard'));
        $this->assertTrue(debug_tracer_should_skip_http_tracing('debug-dashboard/logs/session-uuid'));
        $this->assertTrue(debug_tracer_should_skip_http_tracing('debug/start'));
        $this->assertTrue(debug_tracer_should_skip_http_tracing('debug/stop'));
        $this->assertTrue(debug_tracer_should_skip_http_tracing('debug/export/abc-123'));
        $this->assertTrue(debug_tracer_should_skip_http_tracing('api/debug/start'));
        $this->assertTrue(debug_tracer_should_skip_http_tracing('api/debug/export/abc'));
    }

    public function test_it_does_not_skip_normal_api_paths(): void
    {
        $this->assertFalse(debug_tracer_should_skip_http_tracing('api/orders'));
        $this->assertFalse(debug_tracer_should_skip_http_tracing('v1/users'));
        $this->assertFalse(debug_tracer_should_skip_http_tracing('debug-panel')); // not debug-dashboard
    }
}
