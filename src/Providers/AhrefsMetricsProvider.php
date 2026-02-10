<?php

declare(strict_types=1);

namespace BacklinkChecker\Providers;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Domain\Enum\ProviderType;
use BacklinkChecker\Services\HttpClient;
use BacklinkChecker\Services\ProviderCacheService;

final class AhrefsMetricsProvider implements MetricsProviderInterface
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
            $cached = $this->cache->get(ProviderType::AHREFS, $url);
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

        $apiKey = trim($this->config->string('AHREFS_API_KEY'));
        if ($apiKey === '') {
            foreach ($pending as $url) {
                $results[$url] = ['pa' => null, 'da' => null, 'status' => 'error', 'error' => 'Ahrefs API key not configured'];
            }
            return $results;
        }

        foreach ($pending as $url) {
            $endpoint = rtrim($this->config->string('AHREFS_API_ENDPOINT', 'https://apiv2.ahrefs.com'), '/')
                . '?token=' . urlencode($apiKey)
                . '&target=' . urlencode($url)
                . '&from=domain_rating&mode=domain&output=json';

            $response = $this->http->postJson($endpoint, []);

            if (!($response['ok'] ?? false) || ($response['status'] ?? 0) >= 400) {
                $results[$url] = ['pa' => null, 'da' => null, 'status' => 'error', 'error' => 'Ahrefs API error'];
                continue;
            }

            $data = json_decode((string) ($response['body'] ?? ''), true);
            $dr = isset($data['domain']['domain_rating']) ? round((float) $data['domain']['domain_rating'], 2) : null;
            $ur = isset($data['domain']['url_rating']) ? round((float) $data['domain']['url_rating'], 2) : null;

            $results[$url] = ['pa' => $ur, 'da' => $dr, 'status' => 'ok', 'error' => null];
            $this->cache->put(ProviderType::AHREFS, $url, ['pa' => $ur, 'da' => $dr], $this->config->int('AHREFS_CACHE_TTL_SECONDS', 21600));
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function healthcheck(): array
    {
        return [
            'provider' => 'ahrefs',
            'configured' => trim($this->config->string('AHREFS_API_KEY')) !== '',
            'endpoint' => $this->config->string('AHREFS_API_ENDPOINT', 'https://apiv2.ahrefs.com'),
        ];
    }
}
