<?php

declare(strict_types=1);

namespace BacklinkChecker\Providers;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Domain\Enum\ProviderType;
use BacklinkChecker\Services\HttpClient;
use BacklinkChecker\Services\ProviderCacheService;

final class MajesticMetricsProvider implements MetricsProviderInterface
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
            $cached = $this->cache->get(ProviderType::MAJESTIC, $url);
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

        $apiKey = trim($this->config->string('MAJESTIC_API_KEY'));
        if ($apiKey === '') {
            foreach ($pending as $url) {
                $results[$url] = ['pa' => null, 'da' => null, 'status' => 'error', 'error' => 'Majestic API key not configured'];
            }
            return $results;
        }

        foreach ($pending as $url) {
            $host = parse_url($url, PHP_URL_HOST) ?: $url;
            $endpoint = rtrim($this->config->string('MAJESTIC_API_ENDPOINT', 'https://api.majestic.com/api/json'), '/')
                . '?app_api_key=' . urlencode($apiKey)
                . '&cmd=GetIndexItemInfo'
                . '&items=1&item0=' . urlencode($host)
                . '&datasource=fresh';

            $response = $this->http->postJson($endpoint, []);

            if (!($response['ok'] ?? false) || ($response['status'] ?? 0) >= 400) {
                $results[$url] = ['pa' => null, 'da' => null, 'status' => 'error', 'error' => 'Majestic API error'];
                continue;
            }

            $data = json_decode((string) ($response['body'] ?? ''), true);
            $items = $data['DataTables']['Results']['Data'] ?? [];
            $item = $items[0] ?? [];

            $trustFlow = isset($item['TrustFlow']) ? round((float) $item['TrustFlow'], 2) : null;
            $citationFlow = isset($item['CitationFlow']) ? round((float) $item['CitationFlow'], 2) : null;

            $results[$url] = ['pa' => $citationFlow, 'da' => $trustFlow, 'status' => 'ok', 'error' => null];
            $this->cache->put(ProviderType::MAJESTIC, $url, ['pa' => $citationFlow, 'da' => $trustFlow], $this->config->int('MAJESTIC_CACHE_TTL_SECONDS', 21600));
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function healthcheck(): array
    {
        return [
            'provider' => 'majestic',
            'configured' => trim($this->config->string('MAJESTIC_API_KEY')) !== '',
            'endpoint' => $this->config->string('MAJESTIC_API_ENDPOINT', 'https://api.majestic.com/api/json'),
        ];
    }
}
