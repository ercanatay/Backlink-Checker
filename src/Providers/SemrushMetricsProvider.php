<?php

declare(strict_types=1);

namespace BacklinkChecker\Providers;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Domain\Enum\ProviderType;
use BacklinkChecker\Services\HttpClient;
use BacklinkChecker\Services\ProviderCacheService;

final class SemrushMetricsProvider implements MetricsProviderInterface
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
            $cached = $this->cache->get(ProviderType::SEMRUSH, $url);
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

        $apiKey = trim($this->config->string('SEMRUSH_API_KEY'));
        if ($apiKey === '') {
            foreach ($pending as $url) {
                $results[$url] = ['pa' => null, 'da' => null, 'status' => 'error', 'error' => 'SEMrush API key not configured'];
            }
            return $results;
        }

        foreach ($pending as $url) {
            $host = parse_url($url, PHP_URL_HOST) ?: $url;
            $endpoint = rtrim($this->config->string('SEMRUSH_API_ENDPOINT', 'https://api.semrush.com'), '/')
                . '/?type=domain_rank&key=' . urlencode($apiKey)
                . '&domain=' . urlencode($host)
                . '&database=us&export_columns=Dn,Rk,Or';

            $response = $this->http->postJson($endpoint, []);

            if (!($response['ok'] ?? false) || ($response['status'] ?? 0) >= 400) {
                $results[$url] = ['pa' => null, 'da' => null, 'status' => 'error', 'error' => 'SEMrush API error'];
                continue;
            }

            $data = json_decode((string) ($response['body'] ?? ''), true);
            $score = isset($data['authority_score']) ? round((float) $data['authority_score'], 2) : null;

            $results[$url] = ['pa' => null, 'da' => $score, 'status' => 'ok', 'error' => null];
            $this->cache->put(ProviderType::SEMRUSH, $url, ['pa' => null, 'da' => $score], $this->config->int('SEMRUSH_CACHE_TTL_SECONDS', 21600));
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function healthcheck(): array
    {
        return [
            'provider' => 'semrush',
            'configured' => trim($this->config->string('SEMRUSH_API_KEY')) !== '',
            'endpoint' => $this->config->string('SEMRUSH_API_ENDPOINT', 'https://api.semrush.com'),
        ];
    }
}
