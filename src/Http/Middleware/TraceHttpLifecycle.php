<?php

namespace Akhtar\LaravelDebugTracer\Http\Middleware;

use Akhtar\LaravelDebugTracer\Services\DebugTracer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TraceHttpLifecycle
{
    public function __construct(private readonly DebugTracer $tracer)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! app()->bound('debug.session_id')) {
            return $next($request);
        }

        $start = microtime(true);

        $this->tracer->capture([
            'type' => 'request',
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => $this->normalizeHeaders($request->headers->all()),
            'query_params' => $request->query(),
            'body_params' => $request->except(['password', 'password_confirmation']),
        ]);

        try {
            $response = $next($request);
        } catch (Throwable $throwable) {
            $this->tracer->capture([
                'type' => 'error',
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);

            throw $throwable;
        }

        $this->tracer->capture([
            'type' => 'response',
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            'headers' => $this->normalizeHeaders($response->headers->allPreserveCaseWithoutCookies()),
            'body' => $this->extractResponseBody($response),
        ]);


        return $response;
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $values) {
            $normalized[(string) $key] = implode(', ', $values);
        }

        return $normalized;
    }

    private function extractResponseBody(Response $response): mixed
    {
        if ($response instanceof JsonResponse) {
            return $this->truncateValue($response->getData(true));
        }

        if (! method_exists($response, 'getContent')) {
            return null;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->truncateValue($decoded);
        }

        return $this->truncateValue($content);
    }

    private function truncateValue(mixed $value, int $maxStringLength = 4000): mixed
    {
        if (is_string($value)) {
            if (strlen($value) <= $maxStringLength) {
                return $value;
            }

            return substr($value, 0, $maxStringLength).'... [truncated]';
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->truncateValue($item, $maxStringLength);
            }

            return $result;
        }

        return $value;
    }
}
