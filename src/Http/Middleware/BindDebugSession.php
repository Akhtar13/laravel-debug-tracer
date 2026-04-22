<?php

namespace Akhtar\LaravelDebugTracer\Http\Middleware;

use Akhtar\LaravelDebugTracer\Services\TraceStorage;
use Closure;
use Illuminate\Http\Request;

class BindDebugSession
{
    public function __construct(private readonly TraceStorage $storage)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! config('debug-tracer.enabled', false)) {
            return $next($request);
        }

        $meta = $this->findMatchingSession($request);

        if (! $meta) {
            return $next($request);
        }

        app()->instance('debug.session_id', (string) $meta['session_id']);

        return $next($request);
    }

    private function findMatchingSession(Request $request): ?array
    {
        $apiToken = $request->bearerToken() ?: $request->header(config('debug-tracer.matching_header', 'X-Debug-Token'));
        if (is_string($apiToken) && $apiToken !== '') {
            $session = $this->storage->findActiveSessionForScope('api', $apiToken);
            if ($session) {
                return $session;
            }
        }

        if ($request->hasSession()) {
            $panelSessionId = $request->session()->getId();
            $session = $this->storage->findActiveSessionForScope('panel', $panelSessionId);
            if ($session) {
                return $session;
            }
        }

        return null;
    }
}
