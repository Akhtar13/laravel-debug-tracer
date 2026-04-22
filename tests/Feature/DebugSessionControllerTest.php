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
        $start = $this->postJson('/debug/start', ['match_type' => 'token', 'token' => 'usr_42']);
        $start->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('match_type', 'token');

        $sessionId = $start->json('session_id');

        $stop = $this->postJson('/debug/stop', ['session_id' => $sessionId], ['X-Debug-Token' => 'usr_42']);
        $stop->assertOk()->assertJsonPath('status', 'stopped');
    }

    public function test_it_exports_ndjson(): void
    {
        $start = $this->postJson('/debug/start', ['match_type' => 'token', 'token' => 'usr_77']);
        $sessionId = $start->json('session_id');

        /** @var TraceStorage $storage */
        $storage = app(TraceStorage::class);
        $storage->appendEvent($sessionId, ['type' => 'request', 'url' => '/checkout']);

        $export = $this->get('/debug/export/'.$sessionId, ['X-Debug-Token' => 'usr_77']);
        $export->assertOk();
        $this->assertStringContainsString('application/x-ndjson', $export->headers->get('content-type'));
    }

    public function test_start_requires_token_for_token_match_type(): void
    {
        $response = $this->postJson('/debug/start', ['match_type' => 'token']);

        $response->assertStatus(422);
    }
}
