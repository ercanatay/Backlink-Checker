# Sentinel Journal

## 2024-05-23 - API Login Rate Limiting Gap
**Vulnerability:** API login endpoint (/api/v1/auth/login) was using the general API rate limit (120/min) instead of the strict login rate limit (10/15min).
**Learning:** Manual routing logic for rate limits in App.php makes it easy to miss specific endpoints that need stricter limits.
**Prevention:** Group sensitive endpoints and apply stricter middleware policies explicitly, or centralize rate limit configuration by route.

## 2026-02-08 - SSRF in Webhook Delivery (CRITICAL)
**Vulnerability:** `WebhookService::deliver()` accepted arbitrary user-provided URLs via `POST /api/v1/webhooks/test` and notification webhook delivery, with zero URL validation before making HTTP POST requests.
**Learning:** The `HttpClient` class focuses on reliable HTTP requests (SSL verification, redirect limits, timeouts) but has no SSRF protection. Any service using it with user-controlled URLs is vulnerable. The webhook test endpoint was the most direct vector since the response is partially returned.
**Prevention:** All outbound HTTP requests to user-provided URLs should pass through `SsrfGuard::assertExternalUrl()`. The scan URL fetching (`BacklinkAnalyzerService`) has the same pattern and should be addressed in a follow-up.
