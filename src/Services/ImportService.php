<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Domain\Url\UrlNormalizer;
use BacklinkChecker\Exceptions\ValidationException;

final class ImportService
{
    public function __construct(
        private readonly UrlNormalizer $normalizer,
        private readonly ScanService $scans
    ) {
    }

    /**
     * Import URLs from CSV content and create a scan.
     *
     * @return array{scan_id: int, imported: int, skipped: int}
     */
    public function importFromCsv(int $projectId, int $userId, string $rootDomain, string $csvContent, string $provider = 'moz'): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [];
        $urls = [];
        $skipped = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Support CSV with URL in first column
            $parts = str_getcsv($line);
            $url = trim($parts[0] ?? '');

            if ($url === '' || str_starts_with(strtolower($url), 'url')) {
                // skip header
                continue;
            }

            $normalized = $this->normalizer->normalizeUrl($url);
            if ($normalized !== '') {
                $urls[$normalized] = $normalized;
            } else {
                $skipped++;
            }
        }

        if ($urls === []) {
            throw new ValidationException('No valid URLs found in import data');
        }

        $scanId = $this->scans->createScan($projectId, $userId, $rootDomain, array_values($urls), $provider);

        return [
            'scan_id' => $scanId,
            'imported' => count($urls),
            'skipped' => $skipped,
        ];
    }

    /**
     * Parse sitemap XML and extract URLs.
     *
     * @return array<int, string>
     */
    public function parseFromSitemap(string $xmlContent): array
    {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlContent);
        libxml_clear_errors();

        if ($xml === false) {
            throw new ValidationException('Invalid sitemap XML');
        }

        $urls = [];
        $xml->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $locs = $xml->xpath('//sm:loc') ?: $xml->xpath('//loc') ?: [];
        foreach ($locs as $loc) {
            $url = trim((string) $loc);
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        if ($urls === []) {
            throw new ValidationException('No URLs found in sitemap');
        }

        return $urls;
    }
}
