<?php

declare(strict_types=1);

use BacklinkChecker\Domain\Url\UrlNormalizer;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $normalizer = new UrlNormalizer();

    $t->test('URL normalizer strips www and normalizes scheme', static function () use ($t, $normalizer): void {
        $t->assertSame('https://example.com/path', $normalizer->normalizeUrl('https://www.Example.com/path'));
    });

    $t->test('Root domain extraction works', static function () use ($t, $normalizer): void {
        $t->assertSame('example.com', $normalizer->rootDomain('https://www.example.com'));
    });

    $t->test('Host equivalence checks subdomain', static function () use ($t, $normalizer): void {
        $t->assertTrue($normalizer->hostsEquivalent('blog.example.com', 'example.com'));
    });

    $t->test('Relative URL resolver works', static function () use ($t, $normalizer): void {
        $resolved = $normalizer->resolveUrl('https://example.com/path/page', '/test');
        $t->assertSame('https://example.com/test', $resolved);
    });
};
