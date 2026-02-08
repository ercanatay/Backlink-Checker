<?php

declare(strict_types=1);

namespace BacklinkChecker\Providers;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Domain\Enum\ProviderType;
use BacklinkChecker\Services\HttpClient;
use BacklinkChecker\Services\ProviderCacheService;

final class MozMetricsProvider implements MetricsProviderInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly HttpClient $http,
        private readonly ProviderCacheService $cache
    ) {
    }

    /**
     * @param array<int, string> $urls
     * @return array<string, array{pa: float|null, da: float|null, status: string, error: string|null}>
     */
    public function fetchMetrics(array $urls): array
    {
        $results = [];
        $pending = [];

        foreach ($urls as $url) {
            $cached = $this->cache->get(ProviderType::MOZ, $url);
            if (is_array($cached)) {
                $results[$url] = [
                    'pa' => isset($cached['pa']) ? (float) $cached['pa'] : null,
                    'da' => isset($cached['da']) ? (float) $cached['da'] : null,
                    'status' => 'cached',
                    'error' => null,
                ];
                continue;
            }

            $pending[] = $url;
        }

        if ($pending === []) {
            return $results;
        }

        $accessId = trim($this->config->string('MOZ_ACCESS_ID'));
        $secret = trim($this->config->string('MOZ_SECRET_KEY'));

        if ($accessId === '' || $secret === '') {
            foreach ($pending as $url) {
                $results[$url] = [
                    'pa' => null,
                    'da' => null,
                    'status' => 'error',
                    'error' => 'Moz credentials are not configured',
                ];
            }

            return $results;
        }

        $response = $this->http->postJson(
            $this->config->string('MOZ_API_ENDPOINT'),
            ['targets' => array_values($pending)],
            ['Authorization' => 'Basic ' . base64_encode($accessId . ':' . $secret)]
        );

        if (($response['status'] ?? 0) >= 400 || !$response['ok']) {
            $error = 'Moz API request failed with status ' . ($response['status'] ?? 0);
            foreach ($pending as $url) {
                $results[$url] = ['pa' => null, 'da' => null, 'status' => 'error', 'error' => $error];
            }

            return $results;
        }

        $decoded = json_decode((string) $response['body'], true);
        $rows = $decoded['results'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        foreach ($pending as $index => $url) {
            $row = $rows[$index] ?? null;
            if (!is_array($row)) {
                $results[$url] = ['pa' => null, 'da' => null, 'status' => 'error', 'error' => 'Moz response missing row'];
                continue;
            }

            $pa = isset($row['page_authority']) ? round((float) $row['page_authority'], 2) : null;
            $da = isset($row['domain_authority']) ? round((float) $row['domain_authority'], 2) : null;

            $results[$url] = ['pa' => $pa, 'da' => $da, 'status' => 'ok', 'error' => null];
            $this->cache->put(
                ProviderType::MOZ,
                $url,
                ['pa' => $pa, 'da' => $da],
                $this->config->int('MOZ_CACHE_TTL_SECONDS', 21600)
            );
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function healthcheck(): array
    {
        $configured = trim($this->config->string('MOZ_ACCESS_ID')) !== '' && trim($this->config->string('MOZ_SECRET_KEY')) !== '';

        return [
            'provider' => 'moz',
            'configured' => $configured,
            'endpoint' => $this->config->string('MOZ_API_ENDPOINT'),
        ];
    }
}
