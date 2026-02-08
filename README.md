# Backlink Checker Pro v2

Backlink Checker Pro v2 is a modular PHP platform for backlink auditing at team scale. It includes asynchronous scans, RBAC, API access, scheduling, alerts, export pipelines, and 10-language localization (including Turkish).

## Highlights

- Modular architecture with clear domain/service/controller separation
- Legacy-compatible `/index.php` entrypoint shim
- SQLite persistence with versioned migrations
- Queue-based scan execution with retries and dead-letter behavior
- Team RBAC (`admin`, `editor`, `viewer`)
- CSRF protection, hardened sessions, API token scopes, rate limiting
- Moz provider adapter with cache TTL and health metadata
- Backlink extraction with robust URL normalization and host-equivalent matching
- Robots noindex detection from meta and `X-Robots-Tag`
- Multi-link capture per result and link relation classification
- Historical trend comparison against previous completed scan
- Saved result views and advanced filtering/sorting
- Scheduled scans using supported RRULE patterns
- Alert channels: Email, Slack, signed webhook
- Webhook delivery logging and retry via worker queue
- Export formats: CSV, TSV/TXT, XLSX, JSON
- Opt-in telemetry and configurable retention cleanup
- i18n catalogs with fallback and RTL support for Arabic
- Balanced CI quality gates with lint + automated tests

## Professional Feature Matrix (40)

1. Modular app architecture
2. Composer autoloading (PSR-4)
3. `.env` configuration
4. Startup configuration validation
5. Local authentication
6. Team RBAC
7. CSRF enforcement
8. Scoped API tokens
9. Login/API rate limiting
10. Security headers + cookie hardening
11. Structured error payloads
12. Structured JSON logging + correlation IDs
13. Audit logs
14. SQLite migrations
15. Legacy compatibility shim
16. Background queue
17. Retry/backoff + dead jobs
18. Concurrency chunk controls
19. URL normalization (IDN-aware)
20. Host-equivalent backlink matching
21. Robots directive analysis
22. Absolute URL resolution
23. Link type classification (`dofollow/nofollow/ugc/sponsored`)
24. Multi-link capture
25. Redirect chain + final status capture
26. Moz-first provider abstraction
27. Provider cache TTL
28. Scan trend reporting
29. Advanced result filtering/sorting + saved views
30. CSV/TSV/XLSX/JSON exports
31. Team project/workspace model
32. Recurring schedules
33. Email/Slack/webhook alerts
34. Webhook delivery logs
35. REST API v1
36. Telemetry opt-in
37. Retention cleanup policy
38. 10-language localization + fallback
39. Arabic RTL support
40. Accessibility and keyboard-first UX affordances

## Supported Locales

- `en-US` (default)
- `tr-TR`
- `es-ES`
- `fr-FR`
- `de-DE`
- `it-IT`
- `pt-BR`
- `nl-NL`
- `ru-RU`
- `ar-SA` (RTL)

Catalog format: `resources/lang/{locale}.json` with `_meta` (`version`, `lastReviewed`, `rtl`).

## Tech Stack

- PHP 8.2+
- SQLite (PDO)
- cURL + DOM + intl
- Optional Docker (Nginx + PHP-FPM + worker + scheduler)

## Repository Layout

```text
bootstrap/             # app bootstrap + autoload fallback
src/
  Controllers/         # WebController, ApiController
  Database/            # PDO wrapper + Migrator
  Domain/              # enums + URL/link domain logic
  Http/                # request/response/router/session/rate limiter
  I18n/                # translator + locale detection
  Providers/           # metrics provider adapters (Moz)
  Security/            # CSRF/token/password services
  Services/            # scan queue/export/schedule/notification logic
templates/             # server-rendered UI
resources/lang/        # 10 locale catalogs
migrations/            # SQL schema
bin/                   # operational CLI commands
docs/                  # deep technical docs
public/index.php       # web root entrypoint
index.php              # legacy compatibility shim
```

## Quick Start (Local)

1. Copy environment file:

```bash
cp .env.example .env
```

2. Edit `.env`:
- `APP_KEY`
- `BOOTSTRAP_ADMIN_EMAIL`
- `BOOTSTRAP_ADMIN_PASSWORD`
- `MOZ_ACCESS_ID`
- `MOZ_SECRET_KEY`
- webhook/slack settings if used

3. Run migrations:

```bash
php bin/migrate.php
```

4. Start local web server:

```bash
php -S 127.0.0.1:8080 -t .
```

5. Open:

- `http://127.0.0.1:8080/index.php`

6. Start background processes (separate terminals):

```bash
php bin/worker.php
php bin/scheduler.php
```

## Docker Deployment

```bash
cp .env.example .env
# configure .env

docker compose up --build -d
```

Application URL: `http://localhost:8080`

Services:
- `web` (Nginx)
- `app` (PHP-FPM)
- `worker` (queue consumer)
- `scheduler` (recurring scan trigger)

## Shared Hosting Deployment

1. Upload all files.
2. Point document root to `public/`.
3. Ensure PHP extensions: `pdo_sqlite`, `curl`, `dom`, `intl`.
4. Create `.env` from `.env.example`.
5. Run `php bin/migrate.php` once.
6. Add cron jobs:

```cron
* * * * * php /path/to/bin/scheduler.php
* * * * * php /path/to/bin/worker.php 20
0 3 * * * php /path/to/bin/cleanup.php
```

## Authentication and RBAC

- Bootstraps first admin from `.env` if no users exist.
- Roles:
  - `admin`: full control
  - `editor`: scan + schedule + notification management
  - `viewer`: read-only project/scan access

## API v1

Base: `/api/v1`

- `POST /auth/login`
- `POST /projects`
- `POST /scans`
- `GET /scans/{scanId}`
- `GET /scans/{scanId}/results`
- `POST /scans/{scanId}/cancel`
- `POST /schedules`
- `GET /exports/{exportId}`
- `POST /webhooks/test`

See full details in `/docs/API.md`.

## Queue, Scheduler, and Retention

- Scan creation enqueues `scan.run` jobs.
- Worker processes scans and webhook delivery jobs.
- Scheduler creates scans from due schedule rules.
- Cleanup task purges old operational data based on retention.

Commands:

```bash
php bin/worker.php
php bin/scheduler.php
php bin/cleanup.php
```

## Exports

Per-scan exports are generated on demand and persisted in `storage/exports`:

- CSV
- TSV/TXT
- XLSX
- JSON

## Security Defaults

- Session hardening (`HttpOnly`, `SameSite`, configurable secure cookie)
- CSRF tokens for state-changing forms
- API scopes + token hashing (`sha256`)
- Rate limiting in SQLite for login/API paths
- Strict security headers and CSP
- Audit logging for sensitive actions

## Observability

- JSON logs: `storage/logs/app.log`
- Correlation ID included in response headers and log contexts
- Optional telemetry (`TELEMETRY_ENABLED` or settings toggle)

## Testing and CI

Run locally:

```bash
php tools/lint.php
php tests/run.php
```

CI pipeline:
- `.github/workflows/ci.yml`
- Lint + automated tests on push/pull request

## Key Environment Variables

- `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY`
- `DB_PATH`
- `SESSION_*`, `COOKIE_*`, `SECURITY_FORCE_HTTPS`
- `RATE_LIMIT_LOGIN_PER_15_MIN`, `RATE_LIMIT_API_PER_MIN`
- `MOZ_ACCESS_ID`, `MOZ_SECRET_KEY`, `MOZ_API_ENDPOINT`
- `SCAN_*`, `QUEUE_*`
- `RETENTION_DAYS`
- `WEBHOOK_SIGNING_SECRET`, `SLACK_WEBHOOK_URL`, `SMTP_FROM`
- `TELEMETRY_ENABLED`
- `BOOTSTRAP_ADMIN_*`

## Migration from Legacy Script

- `/index.php` remains available and now boots the modular app.
- Results are persisted in SQLite instead of session-only storage.
- Scans run asynchronously through queue workers.
- UI and API now require authenticated access.

See `/docs/MIGRATION.md`.

## Troubleshooting

### Login fails for bootstrap admin
- Verify `BOOTSTRAP_ADMIN_EMAIL` and `BOOTSTRAP_ADMIN_PASSWORD` in `.env`.
- Ensure database is writable (`data/`).

### Scans stay queued
- Worker is not running. Start `php bin/worker.php`.

### Scheduled scans do not trigger
- Scheduler is not running. Start `php bin/scheduler.php` or cron equivalent.

### Moz DA/PA missing
- Check `MOZ_ACCESS_ID` and `MOZ_SECRET_KEY`.
- Inspect `storage/logs/app.log` for upstream errors.

### Export download missing
- Ensure `storage/exports` is writable.

## License

MIT. See `LICENSE`.
