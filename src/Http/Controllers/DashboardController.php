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

        if (! file_exists($path)) {
            return response()->json([]);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $events = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }

        return response()->json($events);
    }

    private function resolveToken(Request $request): ?string
    {
        return normalize_debug_token(
            $request->input('token')
                ?: $request->bearerToken()
                ?: $request->header(config('debug-tracer.matching_header', 'X-Debug-Token'))
        );
    }
}
