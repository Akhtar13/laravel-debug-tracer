# Laravel Debug Session Tracer

File-based, token-scoped, ephemeral tracing for Laravel applications.

## Install

```bash
composer require akhtar/laravel-debug-tracer
```

## Publish config

```bash
php artisan vendor:publish --tag=debug-tracer-config
```

## Configuration

```php
return [
    'enabled' => false,
    'session_ttl_minutes' => 30,
    'storage_path' => storage_path('debug-traces'),
    'matching_mode' => 'token', // token | user | header
];
```

## Scheduler setup

The package registers the `debug-tracer:cleanup` command. Ensure your Laravel scheduler is running:

```bash
php artisan schedule:work
```

## Queue setup

Jobs automatically receive `debug_session_id` when a traced request dispatches them. Add `\Akhtar\LaravelDebugTracer\Queue\DebugTraceJobMiddleware::class` to job middleware when you want job start/end/failure events.

## Usage flow

1. Start a session and choose the session type:

```json
{
  "session_type": "api",
  "barrier_token": "user-login-token"
}
```

For panel tracing:

```json
{
  "session_type": "panel",
  "panel_session_id": "laravel-session-id-from-gui"
}
```

The user must provide `barrier_token` (api) or `panel_session_id` (panel); the package does not auto-pick these at start.
2. Perform actions under the same barrier token or panel session id.
3. Export logs: `GET /debug/export/{session_id}`
4. Stop session: `POST /debug/stop`

## Storage

Files are written to `storage/debug-traces/`:

- `{session_id}.meta.json`
- `{session_id}.log` (NDJSON, append-only)

Expired/stopped sessions are deleted by the cleanup command.
