# Architecture

Backlink Checker Pro v2 is a modular PHP application using an SSR web UI plus REST API.

## Layers
- `src/App.php`: bootstrap, DI wiring, routing, middleware-like checks, security headers.
- `src/Controllers`: web and API boundary.
- `src/Services`: business workflows (auth, scans, queue, schedules, exports, alerts, retention).
- `src/Providers`: external metric provider adapters (Moz-first).
- `src/Domain`: enums + URL/link domain rules.
- `src/Database`: PDO + migration engine.
- `resources/lang`: ICU-compatible locale catalogs.
- `templates`: SSR views.

## Runtime components
- Web process (`index.php` or `public/index.php`)
- Queue worker (`bin/worker.php`)
- Scheduler loop (`bin/scheduler.php`)
- Retention task (`bin/cleanup.php`)

## Security posture
- Hardened session cookies (`HttpOnly`, `SameSite`, optional secure cookie)
- CSRF token enforcement on state-changing forms
- API bearer token scopes
- Basic request rate limiting in SQLite
- Structured JSON logs and correlation IDs
- CSP + security headers
