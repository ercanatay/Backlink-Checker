<?php

declare(strict_types=1);

namespace BacklinkChecker\Security;

/**
 * Prevents Server-Side Request Forgery (SSRF) by blocking requests to
 * internal/private networks, loopback, link-local, and cloud metadata endpoints.
 */
final class SsrfGuard
{
    /**
     * Validates that a URL targets a public, external address.
     *
     * @throws \InvalidArgumentException if the URL is invalid or targets a private/reserved address
     */
    public static function assertExternalUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            throw new \InvalidArgumentException('Invalid URL');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \InvalidArgumentException('Only HTTP(S) URLs are allowed');
        }

        $host = (string) $parts['host'];

        // Resolve hostname to IP addresses to prevent DNS rebinding bypasses
        $ips = gethostbynamel($host);
        if ($ips === false || $ips === []) {
            throw new \InvalidArgumentException('Unable to resolve hostname');
        }

        foreach ($ips as $ip) {
            if (self::isPrivateOrReserved($ip)) {
                throw new \InvalidArgumentException('URLs targeting internal networks are not allowed');
            }
        }
    }

    private static function isPrivateOrReserved(string $ip): bool
    {
        // FILTER_FLAG_NO_PRIV_RANGE rejects: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, fc00::/7
        // FILTER_FLAG_NO_RES_RANGE rejects: 0.0.0.0/8, 127.0.0.0/8, 169.254.0.0/16, 240.0.0.0/4, ::1, etc.
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
