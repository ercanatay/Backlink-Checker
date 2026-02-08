<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Domain\Enum\LinkType;
use BacklinkChecker\Domain\Url\LinkClassifier;
use BacklinkChecker\Domain\Url\UrlNormalizer;
use DOMDocument;
use DOMXPath;

final class BacklinkAnalyzerService
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly UrlNormalizer $normalizer,
        private readonly LinkClassifier $linkClassifier
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $sourceUrl, string $rootDomain): array
    {
        $response = $this->http->getWithRedirects($sourceUrl);

        $result = [
            'source_url' => $sourceUrl,
            'source_domain' => $this->extractHost($sourceUrl),
            'final_url' => $response['final_url'] ?? $sourceUrl,
            'final_domain' => $this->extractHost((string) ($response['final_url'] ?? $sourceUrl)),
            'http_status' => (int) ($response['status'] ?? 0),
            'fetch_status' => 'ok',
            'redirect_chain' => $response['redirect_chain'] ?? [],
            'robots_noindex' => false,
            'x_robots_noindex' => false,
            'backlink_found' => false,
            'best_link_type' => LinkType::NONE,
            'anchor_text' => null,
            'error_message' => null,
            'links' => [],
        ];

        if (!$response['ok'] || (int) ($response['status'] ?? 0) >= 400) {
            $result['fetch_status'] = 'fetch_error';
            $result['error_message'] = (string) ($response['error'] ?? 'HTTP error: ' . ($response['status'] ?? 0));
            return $result;
        }

        $headers = $response['headers'] ?? [];
        $xRobots = strtolower((string) ($headers['x-robots-tag'] ?? ''));
        if ($xRobots !== '' && str_contains($xRobots, 'noindex')) {
            $result['x_robots_noindex'] = true;
        }

        $html = (string) ($response['body'] ?? '');
        if (trim($html) === '') {
            $result['fetch_status'] = 'empty_body';
            $result['error_message'] = 'Fetched content is empty';
            return $result;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = @$dom->loadHTML($html);
        libxml_clear_errors();

        if (!$loaded) {
            $result['fetch_status'] = 'parse_error';
            $result['error_message'] = 'Failed to parse HTML';
            return $result;
        }

        $xpath = new DOMXPath($dom);

        $noindexMeta = $xpath->query(
            "//meta[(translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='robots' or "
            . "translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='googlebot') and "
            . "contains(translate(@content, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'noindex')]"
        );
        if ($noindexMeta !== false && $noindexMeta->length > 0) {
            $result['robots_noindex'] = true;
        }

        $anchors = $xpath->query('//a[@href]');
        $root = $this->normalizer->rootDomain($rootDomain);
        // Optimization: Pre-calculate host equivalence to skip relative links later
        $isSourceSameAsTarget = $this->normalizer->hostsEquivalent($result['final_domain'], $root);

        if ($anchors === false) {
            return $result;
        }

        $bestWeight = -1;
        foreach ($anchors as $anchor) {
            $href = trim((string) $anchor->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            // Optimization: Skip relative links if source is not target
            $isAbsolute = preg_match('#^https?://#i', $href) || str_starts_with($href, '//');
            if (!$isAbsolute && !$isSourceSameAsTarget) {
                continue;
            }

            $resolved = $this->normalizer->resolveUrl((string) $result['final_url'], $href);
            if ($resolved === '') {
                continue;
            }

            $resolvedHost = $this->extractHost($resolved);
            $isTarget = $this->normalizer->hostsEquivalent($resolvedHost, $root);

            $rel = (string) $anchor->getAttribute('rel');
            $linkType = $this->linkClassifier->classify($rel);
            $anchorText = trim((string) $anchor->textContent);

            if ($isTarget) {
                $result['backlink_found'] = true;
                $result['links'][] = [
                    'href' => $href,
                    'resolved_url' => $resolved,
                    'rel' => $rel,
                    'link_type' => $linkType,
                    'anchor_text' => $anchorText,
                    'is_target' => true,
                ];

                $weight = $this->linkClassifier->weight($linkType);
                if ($weight > $bestWeight) {
                    $bestWeight = $weight;
                    $result['best_link_type'] = $linkType;
                    $result['anchor_text'] = $anchorText !== '' ? $anchorText : null;
                }
            }
        }

        if (!$result['backlink_found']) {
            $result['best_link_type'] = LinkType::NONE;
        }

        return $result;
    }

    private function extractHost(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        return $this->normalizer->normalizeHost((string) $parts['host']);
    }
}
