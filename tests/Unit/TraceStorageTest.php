<?php

namespace Akhtar\LaravelDebugTracer\Tests\Unit;

use Akhtar\LaravelDebugTracer\Services\TraceStorage;
use Akhtar\LaravelDebugTracer\Tests\TestCase;
use Carbon\CarbonImmutable;

class TraceStorageTest extends TestCase
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

    public function test_it_creates_meta_and_appends_events(): void
    {
        $storage = new TraceStorage(config('debug-tracer.storage_path'));

        $meta = $storage->createSessionMeta('abc123', 'usr_1', 'active', CarbonImmutable::now()->addMinutes(30));
        $storage->appendEvent('abc123', ['type' => 'request', 'url' => '/checkout']);
        $storage->appendEvent('abc123', ['type' => 'response', 'status' => 200]);

        $this->assertSame('abc123', $meta['session_id']);
        $this->assertFileExists($storage->metaPath('abc123'));
        $this->assertFileExists($storage->logPath('abc123'));

        $lines = file($storage->logPath('abc123'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(2, $lines);
    }

    public function test_it_finds_active_session_by_scope_type_and_token(): void
    {
        $storage = new TraceStorage(config('debug-tracer.storage_path'));
        $storage->createSessionMeta('api-session', 'abc-token', 'active', CarbonImmutable::now()->addMinutes(30), null, 'api');
        $storage->createSessionMeta('panel-session', 'abc-session-id', 'active', CarbonImmutable::now()->addMinutes(30), null, 'panel');

        $matchedApi = $storage->findActiveSessionForScope('api', 'abc-token');
        $matchedPanel = $storage->findActiveSessionForScope('panel', 'abc-session-id');

        $this->assertSame('api-session', $matchedApi['session_id']);
        $this->assertSame('panel-session', $matchedPanel['session_id']);
    }
}
