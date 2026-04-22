<?php

namespace Akhtar\LaravelDebugTracer\Http\Controllers;

use Akhtar\LaravelDebugTracer\Services\TraceStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebugSessionController extends Controller
{
    public function __construct(private readonly TraceStorage $storage)
    {
    }

    public function start(Request $request): JsonResponse
    {
        abort_unless(config('debug-tracer.enabled', false), 403, 'Debug tracer disabled.');

        $token = $request->input('token', $this->resolveToken($request));
        abort_unless($token, 422, 'Unable to resolve trace token.');

        $sessionId = (string) Str::uuid();
        $expiresAt = CarbonImmutable::now('UTC')->addMinutes((int) config('debug-tracer.session_ttl_minutes', 30));

        $meta = $this->storage->createSessionMeta($sessionId, $token, 'active', $expiresAt);

        return response()->json([
            'session_id' => $sessionId,
            'token' => $token,
            'status' => $meta['status'],
            'expires_at' => $meta['expires_at'],
        ]);
    }

    public function stop(Request $request): JsonResponse
    {
        $sessionId = (string) $request->input('session_id');
        $meta = $this->storage->getSessionMeta($sessionId);

        abort_unless($meta, 404, 'Session not found.');
        abort_if(! $this->isOwner($request, $meta), 403, 'You do not own this session.');

        $updated = $this->storage->updateSessionStatus($sessionId, 'stopped');

        return response()->json([
            'session_id' => $sessionId,
            'status' => $updated['status'],
        ]);
    }

    public function export(Request $request, string $sessionId): StreamedResponse
    {
        $meta = $this->storage->getSessionMeta($sessionId);
        abort_unless($meta, 404, 'Session not found.');
        abort_if(! $this->isOwner($request, $meta), 403, 'You do not own this session.');

        $path = $this->storage->logPath($sessionId);
        abort_unless(is_file($path), 404, 'Log file not found.');

        return response()->streamDownload(
            static function () use ($path): void {
                echo (string) file_get_contents($path);
            },
            'debug-trace-'.$sessionId.'.ndjson',
            ['Content-Type' => 'application/x-ndjson']
        );
    }

    private function isOwner(Request $request, array $meta): bool
    {
        return ($meta['token'] ?? null) === $this->resolveToken($request);
    }

    private function resolveToken(Request $request): ?string
    {
        $mode = config('debug-tracer.matching_mode', 'token');

        return match ($mode) {
            'user' => $request->user() ? 'usr_'.$request->user()->getAuthIdentifier() : null,
            'header' => $request->header(config('debug-tracer.matching_header', 'X-Debug-Token')),
            default => $request->bearerToken() ?: $request->header(config('debug-tracer.matching_header', 'X-Debug-Token')),
        };
    }
}
