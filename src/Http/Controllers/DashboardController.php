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

        $events = $this->filterEventsForDashboard(
            $events,
            $token,
            $request->query('trace_id'),
            filter_var($request->query('show_all', false), FILTER_VALIDATE_BOOLEAN)
        );

        return response()->json($events);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    private function filterEventsForDashboard(array $events, string $token, mixed $traceIdQuery, bool $showAll): array
    {
        $events = array_values(array_filter(
            $events,
            static function (array $e) use ($token): bool {
                if (! array_key_exists('trace_token', $e)) {
                    return true;
                }

                return normalize_debug_token((string) $e['trace_token']) === $token;
            }
        ));

        if ($showAll || ! $this->eventsDefineTraceIds($events)) {
            return $events;
        }

        $traceIdFilter = is_string($traceIdQuery) && $traceIdQuery !== '' ? $traceIdQuery : null;
        if ($traceIdFilter === null) {
            return $events;
        }

        return array_values(array_filter(
            $events,
            static fn (array $e): bool => ($e['trace_id'] ?? null) === $traceIdFilter
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function eventsDefineTraceIds(array $events): bool
    {
        foreach ($events as $e) {
            if (isset($e['trace_id']) && is_string($e['trace_id']) && $e['trace_id'] !== '') {
                return true;
            }
        }

        return false;
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
