<?php

namespace Akhtar\LaravelDebugTracer\Services;

class DebugTracer
{
    public function __construct(private readonly TraceStorage $storage)
    {
    }

    public function capture(array $event): void
    {
        if (! app()->bound('debug.session_id')) {
            return;
        }

        $sessionId = app('debug.session_id');
        $this->storage->appendEvent((string) $sessionId, $event);
    }
}
