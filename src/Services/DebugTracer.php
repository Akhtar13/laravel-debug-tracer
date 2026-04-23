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

        $this->storage->appendEvent((string) app('debug.session_id'), $event);
    }
}
