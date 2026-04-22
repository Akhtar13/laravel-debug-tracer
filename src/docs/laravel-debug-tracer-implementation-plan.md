# Laravel Debug Session Tracer - Implementation Plan

## Goal
Build a Laravel package for **on-demand, token-scoped, file-based debug tracing** with:
- zero database migrations
- zero Redis dependency
- temporary logs only
- queue/job trace continuity
- automatic cleanup

## Guiding Rules
- Debug is **off by default**.
- Trace only the target scope (token/user/header).
- Store data only in `storage/debug-traces/`.
- Keep logs ephemeral with hard expiry + scheduled cleanup.
- Protect all debug endpoints with authentication and ownership checks.

## Phase 0 - Package Foundation
### Scope
Set up package structure, service provider, config file, route registration, and base interfaces.

### Tasks
- Create package directories (`src`, `config`, `routes`, `tests`).
- Add `DebugTracerServiceProvider`.
- Publish config (`debug-tracer.php`) with sane defaults:
  - `enabled` (false)
  - `session_ttl_minutes` (30)
  - `storage_path` (`storage/debug-traces`)
  - `matching_mode` (token/user/header)
- Register API routes for start/stop/export.
- Add `.gitignore` entry guidance for `/storage/debug-traces/*`.

### Done Criteria
- Package installs and auto-discovers provider.
- Config publish command works.
- Routes are visible in `php artisan route:list`.

### Validation
- Manual smoke test: install package in test app and boot without errors.

---

## Phase 1 - File Storage Layer (Meta + NDJSON)
### Scope
Implement low-level file read/write services and directory lifecycle.

### Tasks
- Implement session meta file read/write (`{session_id}.meta.json`).
- Implement append-only NDJSON writer for `{session_id}.log`.
- Ensure atomic appends with `FILE_APPEND | LOCK_EX`.
- Create storage directory automatically if missing.
- Add helper methods:
  - `createSessionMeta()`
  - `getSessionMeta()`
  - `updateSessionStatus()`
  - `appendEvent()`

### Done Criteria
- New session creates both files correctly.
- Event appends one JSON record per line.
- No full-file rewrites for log file.

### Validation
- Unit tests for meta read/write and log append.
- Concurrency test with multiple writes to same log file.

---

## Phase 2 - Session Lifecycle APIs (Start / Stop / Export)
### Scope
Implement core debug session endpoints and ownership model.

### Tasks
- `POST /debug/start`
  - generate `session_id`
  - create empty log file
  - create meta with `status=active`, `expires_at`
- `POST /debug/stop`
  - set `status=stopped`
- `GET /debug/export/{session_id}`
  - auth required
  - ownership check
  - stream/download NDJSON file
- Return standardized JSON response format.

### Done Criteria
- Session can be started and stopped reliably.
- Export works only for authorized owner.

### Validation
- Feature tests for 200/401/403/404 cases.
- Verify downloaded export is valid NDJSON.

---

## Phase 3 - Request Scope Matching Middleware
### Scope
Capture only matching requests and bind active session context.

### Tasks
- Build middleware to:
  - locate target session meta
  - verify `status=active`
  - verify not expired
  - verify token/user/header match
- If matched, bind `debug.session_id` in container.
- If not matched, exit early with near-zero overhead.
- Add hard expiry guard at write time (`expired => do not capture`).

### Done Criteria
- Only scoped requests are traced.
- Expired/stopped sessions produce no new events.

### Validation
- Feature tests for each match mode:
  - bearer token
  - user ID
  - custom header (`X-Debug-Token`)

---

## Phase 4 - HTTP Event Capture Pipeline
### Scope
Capture request/response/exceptions for active debug sessions.

### Tasks
- Capture request event (URL, method, headers subset, timestamp).
- Capture response event (status, duration).
- Capture exception event (message, file, line, trace summary).
- Standardize event schema fields:
  - `type`, `timestamp`, `session_id`, `context`

### Done Criteria
- A traced request writes request + response events.
- Unhandled exceptions are logged before rethrow/handling.

### Validation
- Feature tests with successful and failing endpoints.
- Assert event order is predictable for normal request flow.

---

## Phase 5 - DB and HTTP Client Instrumentation
### Scope
Capture SQL and outbound API calls inside active sessions.

### Tasks
- Add `DB::listen` integration:
  - SQL
  - bindings (optional config toggle)
  - duration
- Add HTTP client event listener integration:
  - URL
  - method
  - status
  - duration
- Ensure instrumentation runs only when debug session is bound.

### Done Criteria
- DB and HTTP client events appear in same session log.
- Non-debug traffic has no instrumentation cost.

### Validation
- Integration tests with fake DB query + HTTP client call.
- Verify no capture when debug not active.

---

## Phase 6 - Queue/Job Trace Continuity
### Scope
Link queued jobs to originating request session and trace job execution.

### Tasks
- Use `Queue::createPayloadUsing()` to inject `debug_session_id`.
- Implement `DebugTraceJobMiddleware`:
  - read `debug_session_id` from payload
  - verify session still active
  - bind `debug.session_id`
  - capture `job_start`, `job_end`, `job_failed`
- Ensure job exceptions are rethrown after logging.

### Done Criteria
- Job events are written to the **same** session log as request.
- Failed jobs include error context.

### Validation
- Queue integration tests with sync and async workers.
- Test dispatch -> execute -> fail flow.

---

## Phase 7 - Cleanup and Expiry Enforcement
### Scope
Prevent log accumulation and guarantee temporary storage behavior.

### Tasks
- Add scheduler task (`everyFiveMinutes`) to:
  - scan `*.meta.json`
  - delete expired or stopped session files (`.meta.json` + `.log`)
- Enforce write-time expiry check in middleware/writer.
- Mark expired sessions with `status=expired` before cleanup (optional but recommended).

### Done Criteria
- Old files are removed automatically.
- Expired sessions cannot append new events.

### Validation
- Time-travel tests for TTL expiry.
- Scheduler command test confirms deletion behavior.

---

## Phase 8 - Security Hardening
### Scope
Secure debug endpoints and reduce accidental exposure.

### Tasks
- Require authentication middleware on all debug routes.
- Enforce session ownership on stop/export.
- Restrict route registration in production by config/environment toggle.
- Sanitize export filename and response headers.

### Done Criteria
- Unauthorized users cannot start/stop/export others' sessions.
- Debug routes are disabled when configured off.

### Validation
- Security-focused feature tests for access control matrix.

---

## Phase 9 - Developer Experience and Documentation
### Scope
Make the package easy to adopt and maintain.

### Tasks
- Write setup docs:
  - install
  - publish config
  - scheduler setup
  - queue middleware usage
- Add usage guide:
  - start session
  - run scenario
  - export logs
  - stop session
- Add troubleshooting section for common issues.
- Provide sample NDJSON event snippets.

### Done Criteria
- New developer can run first trace in <= 15 minutes.

### Validation
- Fresh install walkthrough by another dev.

---

## Phase 10 - Release Readiness
### Scope
Stabilize v1 and publish with confidence.

### Tasks
- Reach target test coverage for critical flows.
- Run compatibility matrix (Laravel + PHP versions).
- Finalize changelog and semantic version (`v1.0.0`).
- Tag release and publish package.

### Done Criteria
- All CI checks pass.
- Stable release published with clear upgrade/install docs.

### Validation
- Pre-release checklist signed off.

---

## Suggested Execution Order (Milestone View)
1. Foundation + Storage (`Phase 0-1`)
2. Session APIs + Matching (`Phase 2-3`)
3. Core Capture + Integrations (`Phase 4-6`)
4. Cleanup + Security (`Phase 7-8`)
5. Docs + Release (`Phase 9-10`)

## Phase-by-Phase Working Method
For each phase, repeat this cycle:
1. Implement only that phase scope.
2. Run phase tests and smoke checks.
3. Demo output (sample logs/API responses).
4. Fix gaps before moving to next phase.
5. Commit with a phase-specific message.
