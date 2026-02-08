# SQLite Schema

Migration file: `migrations/001_init.sql`

## Identity and access
- `users`
- `roles`
- `user_roles`
- `api_tokens`

## Collaboration and projects
- `projects`
- `project_members`
- `notifications`
- `saved_views`

## Scan execution
- `scans`
- `scan_targets`
- `scan_results`
- `scan_links`
- `jobs`

## Integrations and operations
- `provider_cache`
- `exports`
- `schedules`
- `schedule_runs`
- `webhook_deliveries`
- `audit_logs`
- `settings`
- `telemetry_events`
- `rate_limits`
- `i18n_catalog_versions`
- `schema_migrations`

## Data lifecycle
- Retention defaults to 90 days for operational/audit telemetry tables.
- `bin/cleanup.php` enforces retention policy.
