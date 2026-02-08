<?php

declare(strict_types=1);

namespace BacklinkChecker\Config;

final class Config
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(private array $items)
    {
    }

    public static function build(string $rootPath): self
    {
        $defaults = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://localhost:8080',
            'APP_NAME' => 'Backlink Checker Pro v2',
            'APP_KEY' => 'change_me',
            'APP_DEFAULT_LOCALE' => 'en-US',
            'APP_FALLBACK_LOCALE' => 'en-US',
            'APP_TIMEZONE' => 'UTC',
            'DB_PATH' => 'data/app.sqlite',
            'SECURITY_FORCE_HTTPS' => 'false',
            'SESSION_NAME' => 'backlink_pro_session',
            'SESSION_TTL_MINUTES' => '120',
            'COOKIE_SECURE' => 'false',
            'COOKIE_SAMESITE' => 'Lax',
            'RATE_LIMIT_LOGIN_PER_15_MIN' => '10',
            'RATE_LIMIT_API_PER_MIN' => '120',
            'MOZ_API_ENDPOINT' => 'https://lsapi.seomoz.com/v2/url_metrics',
            'MOZ_CACHE_TTL_SECONDS' => '21600',
            'SCAN_TIMEOUT_SECONDS' => '20',
            'SCAN_CONNECT_TIMEOUT_SECONDS' => '10',
            'SCAN_MAX_REDIRECTS' => '5',
            'SCAN_MAX_CONCURRENCY' => '5',
            'SCAN_USER_AGENT' => 'BacklinkCheckerProBot/2.0',
            'QUEUE_BATCH_SIZE' => '5',
            'QUEUE_SLEEP_SECONDS' => '2',
            'QUEUE_MAX_ATTEMPTS' => '3',
            'RETENTION_DAYS' => '90',
            'TELEMETRY_ENABLED' => 'false',
            'WEBHOOK_SIGNING_SECRET' => 'change_webhook_secret',
            'BOOTSTRAP_ADMIN_EMAIL' => 'admin@example.com',
            'BOOTSTRAP_ADMIN_NAME' => 'Administrator',
            'BOOTSTRAP_ADMIN_PASSWORD' => 'ChangeThisNow!',
            'LOG_PATH' => 'storage/logs/app.log',
            'EXPORT_PATH' => 'storage/exports',
        ];

        $items = [];
        foreach ($defaults as $key => $default) {
            $items[$key] = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
        }

        $items['ROOT_PATH'] = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $items['DB_ABSOLUTE_PATH'] = self::makeAbsolutePath($items['ROOT_PATH'], (string) $items['DB_PATH']);
        $items['LOG_ABSOLUTE_PATH'] = self::makeAbsolutePath($items['ROOT_PATH'], (string) $items['LOG_PATH']);
        $items['EXPORT_ABSOLUTE_PATH'] = self::makeAbsolutePath($items['ROOT_PATH'], (string) $items['EXPORT_PATH']);

        return new self($items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) ($this->items[$key] ?? $default);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) ($this->items[$key] ?? $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        $raw = $this->items[$key] ?? $default;
        if (is_bool($raw)) {
            return $raw;
        }

        $value = strtolower((string) $raw);
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private static function makeAbsolutePath(string $root, string $path): string
    {
        if ($path === '') {
            return $root;
        }

        if ($path[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }

        return $root . DIRECTORY_SEPARATOR . $path;
    }
}
