# Architecture

Backlink Checker Pro v2 is a modular PHP application using an SSR web UI plus REST API.

## Layers
- `src/App.php`: bootstrap, DI wiring, routing, middleware-like checks, security headers.
- `src/Controllers`: web and API boundary.
- `src/Services`: business workflows (auth, scans, queue, schedules, exports, alerts, retention, updater).
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
- Updater CLI (`bin/updater.php`)

## Updater flow
- `scheduler.php` periodically enqueues `updater.check` jobs.
- `worker.php` executes `updater.check` and `updater.apply`.
- `UpdaterService` performs:
  - GitHub latest release lookup
  - release/tag validation (`stable semver` only)
  - safe apply pipeline (`git pull --ff-only`, optional `composer install`, migration)
  - DB backup + rollback to previous commit on failure
  - updater state persistence in `settings` (`updater.config`, `updater.state`)

## Security posture
- Hardened session cookies (`HttpOnly`, `SameSite`, optional secure cookie)
- CSRF token enforcement on state-changing forms
- API bearer token scopes
- Basic request rate limiting in SQLite
- Structured JSON logs and correlation IDs
- CSP + security headers
