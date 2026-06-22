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
        $start->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.status', 'active');

        $sessionId = $start->json('data.session_id');

        $stop = $this->postJson('/debug/stop', ['session_id' => $sessionId], ['X-Debug-Token' => 'usr_42']);
        $stop->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.status', 'stopped');

        /** @var TraceStorage $storage */
        $storage = app(TraceStorage::class);
        $meta = $storage->getSessionMeta($sessionId);
        $this->assertSame('stopped', $meta['status']);
        $this->assertFileExists($storage->logPath($sessionId));
    }

    public function test_starting_new_session_clears_previous_session_files(): void
    {
        $first = $this->postJson('/debug/start', ['token' => 'usr_42']);
        $first->assertOk();
        $firstSessionId = $first->json('data.session_id');

        /** @var TraceStorage $storage */
        $storage = app(TraceStorage::class);
        $storage->appendEvent($firstSessionId, ['type' => 'request', 'url' => '/first']);

        $second = $this->postJson('/debug/start', ['token' => 'usr_42']);
        $second->assertOk();

        $this->assertFileDoesNotExist($storage->metaPath($firstSessionId));
        $this->assertFileDoesNotExist($storage->logPath($firstSessionId));
    }

    public function test_it_exports_ndjson(): void
    {
        $start = $this->postJson('/debug/start', ['token' => 'usr_77']);
        $sessionId = $start->json('data.session_id');

        /** @var TraceStorage $storage */
        $storage = app(TraceStorage::class);
        $storage->appendEvent($sessionId, ['type' => 'request', 'url' => '/checkout']);

        $export = $this->get('/debug/export/'.$sessionId, ['X-Debug-Token' => 'usr_77']);
        $export->assertOk();
        $this->assertStringContainsString('application/x-ndjson', $export->headers->get('content-type'));

        $content = $export->streamedContent();
        $lines = array_values(array_filter(explode(PHP_EOL, $content)));
        $this->assertNotEmpty($lines);
        $this->assertIsArray(json_decode($lines[0], true));
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function test_stop_requires_authentication_token(): void
    {
        $start = $this->postJson('/debug/start', ['token' => 'usr_42']);
        $sessionId = $start->json('data.session_id');

        $this->postJson('/debug/stop', ['session_id' => $sessionId])
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_stop_rejects_non_owner(): void
    {
        $start = $this->postJson('/debug/start', ['token' => 'owner']);
        $sessionId = $start->json('data.session_id');

        $this->postJson('/debug/stop', ['session_id' => $sessionId], ['X-Debug-Token' => 'intruder'])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_export_returns_not_found_for_missing_session(): void
    {
        $this->getJson('/debug/export/missing-session', ['X-Debug-Token' => 'owner'])
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_it_matches_bearer_token_formats_for_stop_and_export(): void
    {
        $plain = '97kEy5Q7usVBBDbCKcG0uQzJgAmKMBpqiDVMmGUSd844ce11';
        $withPrefix = '3011|'.$plain;

        $start = $this->postJson('/debug/start', ['token' => 'Bearer '.$withPrefix]);
        $start->assertOk();

        $sessionId = $start->json('data.session_id');

        $this->postJson('/debug/stop', ['session_id' => $sessionId], [
            'Authorization' => 'Bearer '.$withPrefix,
        ])->assertOk();

        $restart = $this->postJson('/debug/start', ['token' => 'Bearer '.$plain]);
        $restart->assertOk();
        $sessionId = $restart->json('data.session_id');

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

        $sessionId = $start->json('data.session_id');

        /** @var TraceStorage $storage */
        $storage = app(TraceStorage::class);
        $storage->appendEvent($sessionId, ['type' => 'request', 'url' => '/products']);

        $logs = $this->getJson('/debug-dashboard/logs/'.$sessionId, [
            'Authorization' => 'Bearer '.$plain,
        ]);
        $logs->assertOk()->assertJsonFragment(['type' => 'request', 'url' => '/products']);
    }
}
