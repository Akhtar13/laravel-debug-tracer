<?php

namespace Akhtar\LaravelDebugTracer\Policies;

class DebugSessionPolicy
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function owns(?string $token, array $meta): bool
    {
        return $token !== null
            && normalize_debug_token($meta['token'] ?? null) === normalize_debug_token($token);
    }
}
