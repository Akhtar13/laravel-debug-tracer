<?php

namespace Akhtar\LaravelDebugTracer\Queue;

use Akhtar\LaravelDebugTracer\Services\DebugTracer;
use Akhtar\LaravelDebugTracer\Services\TraceStorage;
use Closure;
use Throwable;

class DebugTraceJobMiddleware
{
    public function __construct(
        private readonly TraceStorage $storage,
        private readonly DebugTracer $tracer
    ) {
    }

    public function handle($job, Closure $next): mixed
    {
        $payload = method_exists($job, 'payload') ? $job->payload() : [];
        $sessionId = $payload['debug_session_id'] ?? null;

        if (! $sessionId) {
            return $next($job);
        }

        $meta = $this->storage->getSessionMeta((string) $sessionId);
        if (! $meta || ($meta['status'] ?? null) !== 'active') {
            return $next($job);
        }

        app()->instance('debug.session_id', (string) $sessionId);

        $this->tracer->capture([
            'type' => 'job_start',
            'class' => method_exists($job, 'resolveName') ? $job->resolveName() : get_class($job),
        ]);

        try {
            $result = $next($job);
        } catch (Throwable $throwable) {
            $this->tracer->capture([
                'type' => 'job_failed',
                'class' => method_exists($job, 'resolveName') ? $job->resolveName() : get_class($job),
                'message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        $this->tracer->capture([
            'type' => 'job_end',
            'status' => 'completed',
            'class' => method_exists($job, 'resolveName') ? $job->resolveName() : get_class($job),
        ]);

        return $result;
    }
}
