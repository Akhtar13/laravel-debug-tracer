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
        $context = [];
        if (app()->bound('debug.trace_id')) {
            $context['trace_id'] = app('debug.trace_id');
        }
        if (app()->bound('debug.trace_token')) {
            $context['trace_token'] = app('debug.trace_token');
        }

        $this->storage->appendEvent((string) $sessionId, array_merge($event, $context));
    }
}
