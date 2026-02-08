# Bolt's Journal

## 2026-02-08 - SQLite auto-commit overhead in processScan
**Learning:** ScanService::processScan() writes scan_results, scan_links, scan_targets, and scans progress in individual auto-committed statements. With SQLite WAL mode, each auto-commit still requires its own fsync. The chunk-processing loop is the hot path (up to 500 targets x ~13 writes each = ~6,500 fsyncs).
**Action:** Wrap per-chunk DB writes in an explicit transaction to batch fsyncs. Use beginTransaction/commit/rollBack with Throwable catch to ensure the outer error handler can still mark the scan as FAILED if something goes wrong.

## 2024-05-23 - Crawler Relative Link Optimization
**Learning:** When scanning a page for backlinks to a specific target domain, relative links (e.g., `/about`) on a page that is NOT the target domain are always internal to that page's domain, and thus cannot be backlinks to the target. Skipping full URL resolution for these relative links can significantly improve performance (55% faster in benchmarks) by avoiding expensive URL parsing and normalization operations for thousands of irrelevant links.
**Action:** Always check if the current page's host is equivalent to the target host before processing relative links in backlink checkers or crawlers.
