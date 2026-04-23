<?php

return [
    'enabled' => env('DEBUG_TRACER_ENABLED', false),
    'session_ttl_minutes' => (int) env('DEBUG_TRACER_TTL_MINUTES', 30),
    'storage_path' => storage_path('debug-traces'),
    'matching_mode' => env('DEBUG_TRACER_MATCHING_MODE', 'token'), // token | user | header
    'matching_header' => env('DEBUG_TRACER_MATCHING_HEADER', 'X-Debug-Token'),
    'route_middleware' => ['api'],
    'register_routes' => env('DEBUG_TRACER_REGISTER_ROUTES', true),
    'attach_api_middleware' => env('DEBUG_TRACER_ATTACH_API_MIDDLEWARE', true),

    /*
     * Never bind tracing for these routes, even if they carry the same X-Debug-Token / bearer
     * as an active session. Otherwise the debug dashboard and session APIs would be logged and
     * often become the "latest" trace, hiding your real API traffic.
     */
    'exclude_trace_paths_exact' => [
        'debug/start',
        'debug/stop',
        'api/debug/start',
        'api/debug/stop',
    ],
    'exclude_trace_path_prefixes' => [
        'debug-dashboard',
        'debug/export',
        'api/debug/export',
    ],
];
