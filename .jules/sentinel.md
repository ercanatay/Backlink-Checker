# Sentinel Journal

## 2024-05-23 - API Login Rate Limiting Gap
**Vulnerability:** API login endpoint (/api/v1/auth/login) was using the general API rate limit (120/min) instead of the strict login rate limit (10/15min).
**Learning:** Manual routing logic for rate limits in App.php makes it easy to miss specific endpoints that need stricter limits.
**Prevention:** Group sensitive endpoints and apply stricter middleware policies explicitly, or centralize rate limit configuration by route.

## 2026-02-08 - SSRF in Webhook Delivery (CRITICAL)
**Vulnerability:** `WebhookService::deliver()` accepted arbitrary user-provided URLs via `POST /api/v1/webhooks/test` and notification webhook delivery, with zero URL validation before making HTTP POST requests.
**Learning:** The `HttpClient` class focuses on reliable HTTP requests (SSL verification, redirect limits, timeouts) but has no SSRF protection. Any service using it with user-controlled URLs is vulnerable. The webhook test endpoint was the most direct vector since the response is partially returned.
**Prevention:** All outbound HTTP requests to user-provided URLs should pass through `SsrfGuard::assertExternalUrl()`. The scan URL fetching (`BacklinkAnalyzerService`) has the same pattern and should be addressed in a follow-up.

## 2026-02-09 - Path Traversal in Export Downloads (HIGH)
**Vulnerability:** `WebController::downloadExport` and `ApiController::downloadExport` served files based on `file_path` from the database without verifying that the file resides within the allowed export directory.
**Learning:** Even if file paths are generated securely by the application (as `ExportService` does), storing full paths in the database and trusting them later creates a vulnerability if the database is compromised or if there's a logic error elsewhere.
**Prevention:** Always validate file paths at the point of use (download) to ensure they are within the expected directory, treating database values as untrusted input in this context. Use `realpath()` and `str_starts_with()` for robust directory containment checks.
