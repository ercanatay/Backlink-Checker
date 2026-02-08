# Sentinel Journal

## 2024-05-23 - API Login Rate Limiting Gap
**Vulnerability:** API login endpoint (/api/v1/auth/login) was using the general API rate limit (120/min) instead of the strict login rate limit (10/15min).
**Learning:** Manual routing logic for rate limits in App.php makes it easy to miss specific endpoints that need stricter limits.
**Prevention:** Group sensitive endpoints and apply stricter middleware policies explicitly, or centralize rate limit configuration by route.
