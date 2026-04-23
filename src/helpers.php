<?php

use Akhtar\LaravelDebugTracer\Services\DebugTracer;

if (! function_exists('tracer')) {
    function tracer(): DebugTracer
    {
        return app(DebugTracer::class);
    }
}

if (! function_exists('normalize_debug_token')) {
    function normalize_debug_token(?string $token): ?string
    {
        if (! is_string($token)) {
            return null;
        }

        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (str_starts_with(strtolower($token), 'bearer ')) {
            $token = trim(substr($token, 7));
        }

        if (str_contains($token, '|')) {
            $parts = explode('|', $token, 2);
            $token = $parts[1] ?? $parts[0];
        }

        return trim($token) ?: null;
    }
}

if (! function_exists('debug_tracer_should_skip_http_tracing')) {
    function debug_tracer_should_skip_http_tracing(string $path): bool
    {
        $path = trim($path, '/');

        foreach (config('debug-tracer.exclude_trace_paths_exact', []) as $candidate) {
            $candidate = trim((string) $candidate, '/');
            if ($candidate !== '' && $path === $candidate) {
                return true;
            }
        }

        foreach (config('debug-tracer.exclude_trace_path_prefixes', []) as $prefix) {
            $prefix = trim((string) $prefix, '/');
            if ($prefix === '') {
                continue;
            }
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
