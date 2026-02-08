# Deployment

## Option A: Docker (recommended)
```bash
cp .env.example .env
# edit .env values

docker compose up --build -d
```

Services:
- `web` (Nginx on `:8080`)
- `app` (PHP-FPM)
- `worker` (queue consumer)
- `scheduler` (recurring schedule trigger)

## Option B: Shared hosting
1. Upload repository files.
2. Point document root to `public/`.
3. Ensure PHP 8.2+ with extensions: `pdo_sqlite`, `curl`, `dom`, `intl`.
4. Copy `.env.example` to `.env`, configure secrets.
5. Run migrations once: `php bin/migrate.php`.
6. Configure cron:
   - `* * * * * php /path/to/bin/scheduler.php`
   - `* * * * * php /path/to/bin/worker.php 20`
   - `0 3 * * * php /path/to/bin/cleanup.php`
