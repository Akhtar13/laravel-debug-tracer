<?php

namespace Akhtar\LaravelDebugTracer\Http\Middleware;

use Akhtar\LaravelDebugTracer\Services\DebugTracer;
use Closure;
use Illuminate\Http\Request;
use Throwable;

class TraceHttpLifecycle
{
    public function __construct(private readonly DebugTracer $tracer)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! app()->bound('debug.session_id')) {
            return $next($request);
        }

        $start = microtime(true);

        $this->tracer->capture([
            'type' => 'request',
            'url' => $request->path(),
            'method' => $request->method(),
        ]);

        try {
            $response = $next($request);
        } catch (Throwable $throwable) {
            $this->tracer->capture([
                'type' => 'error',
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);

            throw $throwable;
        }

        $this->tracer->capture([
            'type' => 'response',
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);

        if (app()->bound('debug.trace_id')) {
            $response->headers->set('X-Debug-Trace-Id', (string) app('debug.trace_id'));
        }

        return $response;
    }
}
