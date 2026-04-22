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
        $userToken = $request->user() ? 'usr_'.$request->user()->getAuthIdentifier() : null;
        if ($userToken) {
            $session = $this->storage->findActiveSessionForScope('user', $userToken);
            if ($session) {
                return $session;
            }
        }

        $headerToken = $request->header(config('debug-tracer.matching_header', 'X-Debug-Token'));
        if (is_string($headerToken) && $headerToken !== '') {
            $session = $this->storage->findActiveSessionForScope('header', $headerToken);
            if ($session) {
                return $session;
            }
        }

        $bearerToken = $request->bearerToken();
        if (is_string($bearerToken) && $bearerToken !== '') {
            $session = $this->storage->findActiveSessionForScope('token', $bearerToken);
            if ($session) {
                return $session;
            }
        }

        return null;
    }
}
