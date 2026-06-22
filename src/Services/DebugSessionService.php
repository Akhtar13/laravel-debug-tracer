<?php

namespace Akhtar\LaravelDebugTracer\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class DebugSessionService
{
    public function __construct(private readonly TraceStorage $storage)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function start(string $token): array
    {
        $this->storage->clearAllSessions();

        $sessionId = (string) Str::uuid();
        $expiresAt = CarbonImmutable::now('UTC')->addMinutes((int) config('debug-tracer.session_ttl_minutes', 30));

        return $this->storage->createSessionMeta($sessionId, $token, 'active', $expiresAt);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function stop(string $sessionId): ?array
    {
        return $this->storage->updateSessionStatus($sessionId, 'stopped');
    }

    public function exportPath(string $sessionId): ?string
    {
        $path = $this->storage->logPath($sessionId);

        return is_file($path) ? $path : null;
    }
}
