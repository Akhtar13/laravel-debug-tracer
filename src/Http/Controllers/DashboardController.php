<?php

namespace Akhtar\LaravelDebugTracer\Http\Controllers;

use Akhtar\LaravelDebugTracer\Services\TraceStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


class DashboardController extends Controller
{
    public function __construct(private readonly TraceStorage $storage)
    {
    }

    public function index()
    {
        return view('debug-tracer::dashboard');
    }

    public function logs(Request $request, string $sessionId): JsonResponse
    {
        $meta = $this->storage->getSessionMeta($sessionId);
        if (! $meta) {
            return response()->json([]);
        }

        $token = $this->resolveToken($request);
        if (! $token || normalize_debug_token($meta['token'] ?? null) !== $token) {
            return response()->json([], 403);
        }

        $path = $this->storage->logPath($sessionId);

        if (!file_exists($path)) {
            return response()->json([]);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        return response()->json(array_map(fn($l) => json_decode($l, true), $lines));
    }

    private function resolveToken(Request $request): ?string
    {
        $mode = config('debug-tracer.matching_mode', 'token');

        $token = match ($mode) {
            'user' => $request->user() ? 'usr_'.$request->user()->getAuthIdentifier() : null,
            'header' => $request->header(config('debug-tracer.matching_header', 'X-Debug-Token')),
            default => $request->input('token')
                ?: $request->bearerToken()
                ?: $request->header(config('debug-tracer.matching_header', 'X-Debug-Token')),
        };

        return normalize_debug_token($token);
    }
}
