<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;

final class ProviderCacheService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $provider, string $key): ?array
    {
        $cacheKey = $this->cacheKey($provider, $key);
        $row = $this->db->fetchOne('SELECT value_json, expires_at FROM provider_cache WHERE provider = ? AND cache_key = ?', [$provider, $cacheKey]);
        if ($row === null) {
            return null;
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            $this->db->execute('DELETE FROM provider_cache WHERE provider = ? AND cache_key = ?', [$provider, $cacheKey]);
            return null;
        }

        $decoded = json_decode((string) $row['value_json'], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $value
     */
    public function put(string $provider, string $key, array $value, int $ttlSeconds): void
    {
        $cacheKey = $this->cacheKey($provider, $key);
        $this->db->execute(
            'INSERT INTO provider_cache(provider, cache_key, value_json, expires_at, created_at) VALUES (?, ?, ?, ?, ?) '
            . 'ON CONFLICT(cache_key) DO UPDATE SET value_json = excluded.value_json, expires_at = excluded.expires_at',
            [$provider, $cacheKey, json_encode($value), gmdate('c', time() + $ttlSeconds), gmdate('c')]
        );
    }

    private function cacheKey(string $provider, string $key): string
    {
        return hash('sha256', $provider . ':' . $key);
    }
}
