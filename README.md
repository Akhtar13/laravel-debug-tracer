# Laravel Debug Session Tracer

Minimal, file-based API lifecycle tracing for Laravel.

This package is now focused only on tracing API request/response/error lifecycle events.
No Trace ID filtering, no admin/debug extras, and no job debugging.

## Install

```bash
composer require akhtar/laravel-debug-tracer
```

## Publish configuration

```bash
php artisan vendor:publish --tag=debug-tracer-config
```

## Required configuration

Set these in `.env`:

```dotenv
DEBUG_TRACER_ENABLED=true
DEBUG_TRACER_TTL_MINUTES=30
DEBUG_TRACER_MATCHING_HEADER=X-Debug-Token
```

Config (`config/debug-tracer.php`) options used in this minimal mode:

- `enabled`
- `session_ttl_minutes`
- `storage_path`
- `matching_header`
- `route_middleware`
- `register_routes`
- `attach_api_middleware`

## Scheduler setup (recommended)

The package provides `debug-tracer:cleanup` and auto-schedules it every 5 minutes.

Make sure your app scheduler is running in your environment:

```bash
php artisan schedule:work
```

## Routes

- `POST /debug/start`
- `POST /debug/stop`
- `GET /debug/export/{session_id}`
- `GET /debug-dashboard`
- `GET /debug-dashboard/logs/{session_id}`

## Lifecycle behavior

### Start session

`POST /debug/start`

Body:

```json
{
  "token": "Bearer 3011|your-api-token"
}
```

What happens:

1. All old session files in storage are removed.
2. A new active session is created.
3. New API lifecycle events are appended to this session file.

### Stop session

`POST /debug/stop`

Body:

```json
{
  "session_id": "uuid"
}
```

Header:

```http
X-Debug-Token: your-api-token
```

What happens:

1. Session ownership is validated by token.
2. Session meta/log files are deleted immediately.
3. Export is no longer possible for that stopped session.

### Export logs

`GET /debug/export/{session_id}?token=Bearer your-api-token`

Returns NDJSON of captured API lifecycle entries.

## Dashboard behavior

In `/debug-dashboard`:

- Start button is disabled while a session is active.
- Stop and Export are disabled until a session is active.
- After stop, Stop and Export are disabled again.
- The UI only shows API request/response/error lifecycle details.

## Storage

Default path:

- `storage/debug-traces/{session_id}.meta.json`
- `storage/debug-traces/{session_id}.log`

On new session start, all old files are deleted first.
On stop, current session files are deleted immediately.
