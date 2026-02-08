# Provider Adapter Development

Current implementation ships with `MozMetricsProvider` and the interface:

```php
interface MetricsProviderInterface {
  public function fetchMetrics(array $urls): array;
  public function healthcheck(): array;
}
```

## Add a provider
1. Create class under `src/Providers` implementing `MetricsProviderInterface`.
2. Return keyed map by URL with:
   - `pa`
   - `da`
   - `status`
   - `error`
3. Reuse `ProviderCacheService` for TTL caching.
4. Wire provider in `src/App.php` and optionally expose via UI/API `provider` selector.

## Failure handling
- Provider failures should not crash scan pipelines.
- Store provider error in `scan_results.error_message` and `provider_status`.
