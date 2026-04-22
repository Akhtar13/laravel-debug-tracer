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
            'match_type' => ['nullable', 'in:token,user,header'],
            'token' => ['nullable', 'string'],
        ]);
        $validator->validate();

        $matchType = (string) $request->input('match_type', config('debug-tracer.matching_mode', 'token'));
        $token = $this->resolveTokenForType($request, $matchType);
        abort_unless($token, 422, 'Unable to resolve trace token.');

        $sessionId = (string) Str::uuid();
        $expiresAt = CarbonImmutable::now('UTC')->addMinutes((int) config('debug-tracer.session_ttl_minutes', 30));
        $ownerId = $request->user() ? (string) $request->user()->getAuthIdentifier() : null;

        $meta = $this->storage->createSessionMeta($sessionId, $token, 'active', $expiresAt, $ownerId, $matchType);

        return response()->json([
            'session_id' => $sessionId,
            'token' => $token,
            'match_type' => $meta['match_type'],
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

        $matchType = (string) ($meta['match_type'] ?? config('debug-tracer.matching_mode', 'token'));

        return ($meta['token'] ?? null) === $this->resolveTokenForType($request, $matchType);
    }

    private function resolveTokenForType(Request $request, string $mode): ?string
    {
        return match ($mode) {
            'user' => $request->user() ? 'usr_'.$request->user()->getAuthIdentifier() : null,
            'header' => $request->input('token') ?: $request->header(config('debug-tracer.matching_header', 'X-Debug-Token')),
            default => $request->input('token') ?: $request->bearerToken() ?: $request->header(config('debug-tracer.matching_header', 'X-Debug-Token')),
        };
    }
}
