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

        $token = $this->resolveToken($request);

        if (! $token) {
            return $next($request);
        }

        $meta = $this->storage->findActiveSessionForToken($token);

        if (! $meta) {
            return $next($request);
        }

        app()->instance('debug.session_id', (string) $meta['session_id']);

        return $next($request);
    }

    private function resolveToken(Request $request): ?string
    {
        $mode = config('debug-tracer.matching_mode', 'token');

        return match ($mode) {
            'user' => $request->user() ? 'usr_'.$request->user()->getAuthIdentifier() : null,
            'header' => $request->header(config('debug-tracer.matching_header', 'X-Debug-Token')),
            default => $request->input('token')
                ?: $request->bearerToken()
                ?: $request->header(config('debug-tracer.matching_header', 'X-Debug-Token')),
        };
    }
}
