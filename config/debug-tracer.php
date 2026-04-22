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
];
