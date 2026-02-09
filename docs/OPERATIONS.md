# Operations Runbook

## Core commands
- Migrate: `php bin/migrate.php`
- Worker: `php bin/worker.php`
- Scheduler: `php bin/scheduler.php`
- Cleanup: `php bin/cleanup.php`
- Updater status: `php bin/updater.php status`
- Updater check: `php bin/updater.php check`
- Updater apply: `php bin/updater.php apply`

## Monitoring points
- Queue depth: `SELECT status, COUNT(*) FROM jobs GROUP BY status;`
- Stuck running scans: `SELECT * FROM scans WHERE status='running';`
- Failed jobs: `SELECT * FROM jobs WHERE status='dead' ORDER BY id DESC LIMIT 50;`
- Updater job health: `SELECT id, type, status, attempts, last_error, created_at FROM jobs WHERE type IN ('updater.check', 'updater.apply') ORDER BY id DESC LIMIT 20;`
- Webhook delivery health: `SELECT success, COUNT(*) FROM webhook_deliveries GROUP BY success;`

## Incident checklist
1. Confirm database writeability (`data/*.sqlite`).
2. Confirm worker process is running.
3. Verify Moz credentials in `.env`.
4. Inspect `storage/logs/app.log` with correlation IDs.
5. Requeue dead jobs manually if root cause fixed.
6. If updater apply failed, inspect updater state from dashboard or `php bin/updater.php status` and verify git working tree is clean.

## Retention
Default retention is 90 days. Adjust via `.env` (`RETENTION_DAYS`) and UI settings.
