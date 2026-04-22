<?php

namespace Akhtar\LaravelDebugTracer\Http\Controllers;

use Akhtar\LaravelDebugTracer\Services\TraceStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
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

        $validator = Validator::make($request->all(), [
            'session_type' => ['required', 'in:api,panel'],
            'barrier_token' => ['required_if:session_type,api', 'nullable', 'string'],
            'panel_session_id' => ['required_if:session_type,panel', 'nullable', 'string'],
        ]);
        $validator->validate();

        $sessionType = (string) $request->input('session_type');
        $scopeValue = $sessionType === 'api'
            ? (string) $request->input('barrier_token')
            : (string) $request->input('panel_session_id');

        $sessionId = (string) Str::uuid();
        $expiresAt = CarbonImmutable::now('UTC')->addMinutes((int) config('debug-tracer.session_ttl_minutes', 30));
        $ownerId = $request->user() ? (string) $request->user()->getAuthIdentifier() : null;

        $meta = $this->storage->createSessionMeta($sessionId, $scopeValue, 'active', $expiresAt, $ownerId, $sessionType);

        return response()->json([
            'session_id' => $sessionId,
            'session_type' => $meta['match_type'],
            'scope_value' => $meta['token'],
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
        $ownerId = $meta['owner_id'] ?? null;

        if ($ownerId !== null) {
            return $request->user() && (string) $request->user()->getAuthIdentifier() === (string) $ownerId;
        }

        $matchType = (string) ($meta['match_type'] ?? 'api');

        if ($matchType === 'panel') {
            return ($meta['token'] ?? null) === ($request->hasSession() ? $request->session()->getId() : null);
        }

        return ($meta['token'] ?? null) === ($request->bearerToken() ?: $request->header(config('debug-tracer.matching_header', 'X-Debug-Token')));
    }
}
