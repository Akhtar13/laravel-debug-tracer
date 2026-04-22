<?php

namespace Akhtar\LaravelDebugTracer\Tests\Feature;

use Akhtar\LaravelDebugTracer\Services\TraceStorage;
use Akhtar\LaravelDebugTracer\Tests\TestCase;

class DebugSessionControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $path = config('debug-tracer.storage_path');
        if (is_dir($path)) {
            foreach (glob($path.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($path);
        }

        parent::tearDown();
    }

    public function test_it_starts_and_stops_a_session(): void
    {
        $start = $this->postJson('/debug/start', ['session_type' => 'api', 'barrier_token' => 'usr_42']);
        $start->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('session_type', 'api')
            ->assertJsonPath('scope_value', 'usr_42');

        $sessionId = $start->json('session_id');

        $stop = $this->postJson('/debug/stop', ['session_id' => $sessionId], ['X-Debug-Token' => 'usr_42']);
        $stop->assertOk()->assertJsonPath('status', 'stopped');
    }

    public function test_it_exports_ndjson(): void
    {
        $start = $this->postJson('/debug/start', ['session_type' => 'api', 'barrier_token' => 'usr_77']);
        $sessionId = $start->json('session_id');

        /** @var TraceStorage $storage */
        $storage = app(TraceStorage::class);
        $storage->appendEvent($sessionId, ['type' => 'request', 'url' => '/checkout']);

        $export = $this->get('/debug/export/'.$sessionId, ['X-Debug-Token' => 'usr_77']);
        $export->assertOk();
        $this->assertStringContainsString('application/x-ndjson', $export->headers->get('content-type'));
    }

    public function test_start_requires_barrier_token_for_api_session_type(): void
    {
        $response = $this->postJson('/debug/start', ['session_type' => 'api']);

        $response->assertStatus(422);
    }

    public function test_start_requires_panel_session_id_for_panel_session_type(): void
    {
        $response = $this->postJson('/debug/start', ['session_type' => 'panel']);

        $response->assertStatus(422);
    }
}
