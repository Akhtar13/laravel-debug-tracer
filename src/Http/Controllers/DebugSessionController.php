<?php

namespace Akhtar\LaravelDebugTracer\Http\Controllers;

use Akhtar\LaravelDebugTracer\Http\Requests\ExportDebugSessionRequest;
use Akhtar\LaravelDebugTracer\Http\Requests\StartDebugSessionRequest;
use Akhtar\LaravelDebugTracer\Http\Requests\StopDebugSessionRequest;
use Akhtar\LaravelDebugTracer\Policies\DebugSessionPolicy;
use Akhtar\LaravelDebugTracer\Services\DebugSessionService;
use Akhtar\LaravelDebugTracer\Services\TraceStorage;
use Akhtar\LaravelDebugTracer\Support\DebugTracerResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebugSessionController extends Controller
{
    public function __construct(
        private readonly DebugSessionService $sessions,
        private readonly TraceStorage $storage,
        private readonly DebugSessionPolicy $policy,
    ) {
    }

    public function start(StartDebugSessionRequest $request): JsonResponse
    {
        if (! config('debug-tracer.enabled', false)) {
            return DebugTracerResponse::error('Debug tracer disabled.', 403);
        }

        $token = $request->traceToken();
        if (! $token) {
            return DebugTracerResponse::error('Unable to resolve trace token.', 422);
        }

        $meta = $this->sessions->start($token);

        return DebugTracerResponse::success([
            'session_id' => $meta['session_id'],
            'token' => $meta['token'],
            'status' => $meta['status'],
            'expires_at' => $meta['expires_at'],
        ], 'Debug session started.');
    }

    public function stop(StopDebugSessionRequest $request): JsonResponse
    {
        $sessionId = $request->sessionId();
        $meta = $this->storage->getSessionMeta($sessionId);

        if (! $meta) {
            return DebugTracerResponse::error('Session not found.', 404);
        }

        $token = $request->traceToken();
        if (! $token) {
            return DebugTracerResponse::error('Authentication token required.', 401);
        }

        if (! $this->policy->owns($token, $meta)) {
            return DebugTracerResponse::error('You do not own this session.', 403);
        }

        $meta = $this->sessions->stop($sessionId);

        return DebugTracerResponse::success([
            'session_id' => $sessionId,
            'status' => $meta['status'] ?? 'stopped',
        ], 'Debug session stopped.');
    }

    public function export(ExportDebugSessionRequest $request, string $sessionId): JsonResponse|StreamedResponse
    {
        $meta = $this->storage->getSessionMeta($sessionId);

        if (! $meta) {
            return DebugTracerResponse::error('Session not found.', 404);
        }

        $token = $request->traceToken();
        if (! $token) {
            return DebugTracerResponse::error('Authentication token required.', 401);
        }

        if (! $this->policy->owns($token, $meta)) {
            return DebugTracerResponse::error('You do not own this session.', 403);
        }

        $path = $this->sessions->exportPath($sessionId);
        if (! $path) {
            return DebugTracerResponse::error('Log file not found.', 404);
        }

        return response()->streamDownload(
            static function () use ($path): void {
                $handle = fopen($path, 'rb');
                if ($handle === false) {
                    return;
                }

                while (! feof($handle)) {
                    echo (string) fread($handle, 8192);
                }

                fclose($handle);
            },
            'debug-trace-'.$sessionId.'.ndjson',
            ['Content-Type' => 'application/x-ndjson']
        );
    }
}
