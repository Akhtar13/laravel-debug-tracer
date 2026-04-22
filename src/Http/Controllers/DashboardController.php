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

        $token = normalize_debug_token((string) $request->query('token', ''));
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
}
