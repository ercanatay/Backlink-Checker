# Migration Notes: v1 -> v2

## What changed
- Legacy single-file script replaced by modular architecture.
- `/index.php` remains available as compatibility shim.
- Session-only result state replaced with SQLite persistence.
- Sync scans replaced by queue-driven background processing.
- Added RBAC, tokenized API, scheduling, alerts, exports, localization.

## Legacy behavior compatibility
- Existing users can still access app via `/index.php`.
- Web workflow now requires authentication.
- Exports are generated per scan and stored in `storage/exports`.

## Required actions after upgrade
1. Configure `.env` from `.env.example`.
2. Set secure admin credentials.
3. Run `php bin/migrate.php`.
4. Start worker and scheduler processes.
