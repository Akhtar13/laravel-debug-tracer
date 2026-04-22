<?php

use Akhtar\LaravelDebugTracer\Services\DebugTracer;

if (! function_exists('tracer')) {
    function tracer(): DebugTracer
    {
        return app(DebugTracer::class);
    }
}
