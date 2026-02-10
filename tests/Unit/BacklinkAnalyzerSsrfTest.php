<?php

declare(strict_types=1);

use BacklinkChecker\Config\Config;
use BacklinkChecker\Domain\Url\LinkClassifier;
use BacklinkChecker\Domain\Url\UrlNormalizer;
use BacklinkChecker\Services\BacklinkAnalyzerService;
use BacklinkChecker\Services\HttpClient;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $config = new Config([]);
    $http = new HttpClient($config);
    $normalizer = new UrlNormalizer();
    $classifier = new LinkClassifier();
    $analyzer = new BacklinkAnalyzerService($http, $normalizer, $classifier);

    $t->test('Analyzer blocks private IP addresses (SSRF protection)', static function () use ($t, $analyzer): void {
        $privateUrl = 'http://127.0.0.1/admin';

        try {
            $result = $analyzer->analyze($privateUrl, 'example.com');

            // If we reach here, check if it failed due to SSRF or if it actually tried to fetch
            if (($result['fetch_status'] ?? '') === 'fetch_error' && str_contains((string)($result['error_message'] ?? ''), 'internal networks')) {
                // This is what we WANT after the fix
                return;
            }

            // Before the fix, it might return fetch_error due to connection refused, but NOT SSRF message
             if (($result['fetch_status'] ?? '') === 'fetch_error') {
                throw new \RuntimeException('Request failed but not due to SSRF protection: ' . ($result['error_message'] ?? 'Unknown error'));
            }

             throw new \RuntimeException('Request should have been blocked by SSRF protection');

        } catch (\InvalidArgumentException $e) {
             // If the implementation throws exception instead of returning error array
             if (str_contains($e->getMessage(), 'internal networks')) {
                 return;
             }
             throw $e;
        }
    });
};
