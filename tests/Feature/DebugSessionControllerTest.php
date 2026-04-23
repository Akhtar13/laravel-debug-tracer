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
        $start = $this->postJson('/debug/start', ['token' => 'usr_42']);
        $start->assertOk()->assertJsonPath('status', 'active');

        $sessionId = $start->json('session_id');

        $stop = $this->postJson('/debug/stop', ['session_id' => $sessionId], ['X-Debug-Token' => 'usr_42']);
        $stop->assertOk()->assertJsonPath('status', 'stopped');
    }

    public function test_it_exports_ndjson(): void
    {
        $start = $this->postJson('/debug/start', ['token' => 'usr_77']);
        $sessionId = $start->json('session_id');

        /** @var TraceStorage $storage */
        $storage = app(TraceStorage::class);
        $storage->appendEvent($sessionId, ['type' => 'request', 'url' => '/checkout']);

        $export = $this->get('/debug/export/'.$sessionId, ['X-Debug-Token' => 'usr_77']);
        $export->assertOk();
        $this->assertStringContainsString('application/x-ndjson', $export->headers->get('content-type'));
    }

    public function test_it_matches_bearer_token_formats_for_stop_and_export(): void
    {
        $plain = '97kEy5Q7usVBBDbCKcG0uQzJgAmKMBpqiDVMmGUSd844ce11';
        $withPrefix = '3011|'.$plain;

        $start = $this->postJson('/debug/start', ['token' => 'Bearer '.$withPrefix]);
        $start->assertOk();

        $sessionId = $start->json('session_id');

        $this->postJson('/debug/stop', ['session_id' => $sessionId], [
            'Authorization' => 'Bearer '.$withPrefix,
        ])->assertOk();

        $restart = $this->postJson('/debug/start', ['token' => 'Bearer '.$plain]);
        $restart->assertOk();
        $sessionId = $restart->json('session_id');

        /** @var TraceStorage $storage */
        $storage = app(TraceStorage::class);
        $storage->appendEvent($sessionId, ['type' => 'request', 'url' => '/orders']);

        $export = $this->get('/debug/export/'.$sessionId.'?token=Bearer '.$withPrefix);
        $export->assertOk();
        $this->assertStringContainsString('application/x-ndjson', $export->headers->get('content-type'));
    }

    public function test_dashboard_logs_accepts_normalized_token_formats(): void
    {
        $plain = 'dVZxk5aQ7usVBBDbCKcG0uQzJgAmKMBpqiDVMmGUSd844ce11';
        $withPrefix = '9011|'.$plain;

        $start = $this->postJson('/debug/start', ['token' => 'Bearer '.$withPrefix]);
        $start->assertOk();

        $sessionId = $start->json('session_id');

        /** @var TraceStorage $storage */
        $storage = app(TraceStorage::class);
        $storage->appendEvent($sessionId, ['type' => 'request', 'url' => '/products']);

        $logs = $this->getJson('/debug-dashboard/logs/'.$sessionId, [
            'Authorization' => 'Bearer '.$plain,
        ]);
        $logs->assertOk()->assertJsonFragment(['type' => 'request', 'url' => '/products']);
    }

    public function test_dashboard_logs_defaults_to_latest_trace_id(): void
    {
        $plain = 'traceFilterToken123';
        $start = $this->postJson('/debug/start', ['token' => $plain]);
        $start->assertOk();
        $sessionId = $start->json('session_id');

        /** @var TraceStorage $storage */
        $storage = app(TraceStorage::class);
        $storage->appendEvent($sessionId, [
            'type' => 'request',
            'url' => '/first',
            'trace_id' => '11111111-1111-1111-1111-111111111111',
            'trace_token' => $plain,
        ]);
        $storage->appendEvent($sessionId, [
            'type' => 'response',
            'trace_id' => '11111111-1111-1111-1111-111111111111',
            'trace_token' => $plain,
        ]);
        $storage->appendEvent($sessionId, [
            'type' => 'request',
            'url' => '/second',
            'trace_id' => '22222222-2222-2222-2222-222222222222',
            'trace_token' => $plain,
        ]);
        $storage->appendEvent($sessionId, [
            'type' => 'db',
            'trace_id' => '22222222-2222-2222-2222-222222222222',
            'trace_token' => $plain,
        ]);

        $logs = $this->getJson('/debug-dashboard/logs/'.$sessionId, [
            'X-Debug-Token' => $plain,
        ]);
        $logs->assertOk();
        $latestOnly = $logs->json();
        $this->assertCount(2, $latestOnly);
        $this->assertSame('/second', $latestOnly[0]['url'] ?? null);
        $this->assertNotContains('/first', array_column($latestOnly, 'url'));

        $all = $this->getJson('/debug-dashboard/logs/'.$sessionId.'?show_all=1', [
            'X-Debug-Token' => $plain,
        ]);
        $all->assertOk();
        $this->assertCount(4, $all->json());

        $pinned = $this->getJson(
            '/debug-dashboard/logs/'.$sessionId.'?trace_id=11111111-1111-1111-1111-111111111111',
            ['X-Debug-Token' => $plain]
        );
        $pinned->assertOk();
        $this->assertCount(2, $pinned->json());
        $pinned->assertJsonFragment(['url' => '/first']);
    }
}
