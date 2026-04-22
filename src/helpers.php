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
