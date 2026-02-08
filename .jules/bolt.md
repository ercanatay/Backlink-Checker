# Bolt's Journal

## 2026-02-08 - SQLite auto-commit overhead in processScan
**Learning:** ScanService::processScan() writes scan_results, scan_links, scan_targets, and scans progress in individual auto-committed statements. With SQLite WAL mode, each auto-commit still requires its own fsync. The chunk-processing loop is the hot path (up to 500 targets x ~13 writes each = ~6,500 fsyncs).
**Action:** Wrap per-chunk DB writes in an explicit transaction to batch fsyncs. Use beginTransaction/commit/rollBack with Throwable catch to ensure the outer error handler can still mark the scan as FAILED if something goes wrong.
