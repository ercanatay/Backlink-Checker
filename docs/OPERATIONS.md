# Operations Runbook

## Core commands
- Migrate: `php bin/migrate.php`
- Worker: `php bin/worker.php`
- Scheduler: `php bin/scheduler.php`
- Cleanup: `php bin/cleanup.php`

## Monitoring points
- Queue depth: `SELECT status, COUNT(*) FROM jobs GROUP BY status;`
- Stuck running scans: `SELECT * FROM scans WHERE status='running';`
- Failed jobs: `SELECT * FROM jobs WHERE status='dead' ORDER BY id DESC LIMIT 50;`
- Webhook delivery health: `SELECT success, COUNT(*) FROM webhook_deliveries GROUP BY success;`

## Incident checklist
1. Confirm database writeability (`data/*.sqlite`).
2. Confirm worker process is running.
3. Verify Moz credentials in `.env`.
4. Inspect `storage/logs/app.log` with correlation IDs.
5. Requeue dead jobs manually if root cause fixed.

## Retention
Default retention is 90 days. Adjust via `.env` (`RETENTION_DAYS`) and UI settings.
