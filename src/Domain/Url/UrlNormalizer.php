<?php

declare(strict_types=1);

namespace BacklinkChecker\Domain\Url;

final class UrlNormalizer
{
    public function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = $this->normalizeHost((string) $parts['host']);
        if ($host === '') {
            return '';
        }

        $path = isset($parts['path']) ? $this->normalizePath((string) $parts['path']) : '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $path . $query;
    }

    public function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^www\.#', '', $host) ?: $host;

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                $host = strtolower($ascii);
            }
        }

        return trim($host, '.');
    }

    public function rootDomain(string $domainOrUrl): string
    {
        $candidate = trim($domainOrUrl);
        if ($candidate === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $candidate)) {
            $parts = parse_url($candidate);
            $candidate = (string) ($parts['host'] ?? '');
        }

        return $this->normalizeHost($candidate);
    }

    public function hostsEquivalent(string $a, string $b): bool
    {
        $hostA = $this->normalizeHost($a);
        $hostB = $this->normalizeHost($b);

        if ($hostA === '' || $hostB === '') {
            return false;
        }

        return $hostA === $hostB || str_ends_with($hostA, '.' . $hostB) || str_ends_with($hostB, '.' . $hostA);
    }

    public function resolveUrl(string $baseUrl, string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $href)) {
            return $this->normalizeUrl($href);
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            return $this->normalizeUrl($scheme . ':' . $href);
        }

        $base = parse_url($baseUrl);
        if (!is_array($base) || empty($base['host'])) {
            return '';
        }

        $scheme = (string) ($base['scheme'] ?? 'https');
        $host = (string) $base['host'];

        if (str_starts_with($href, '/')) {
            return $this->normalizeUrl($scheme . '://' . $host . $href);
        }

        $basePath = (string) ($base['path'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
        $dir = $dir === '' ? '' : $dir;

        return $this->normalizeUrl($scheme . '://' . $host . $dir . '/' . ltrim($href, '/'));
    }

    private function normalizePath(string $path): string
    {
        $path = preg_replace('#/+#', '/', $path) ?: $path;
        if ($path === '') {
            return '/';
        }

        return '/' . ltrim($path, '/');
    }
}
