<?php

declare(strict_types=1);

use BacklinkChecker\Security\SsrfGuard;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $t->test('SSRF guard blocks localhost', static function () use ($t): void {
        $threw = false;
        try {
            SsrfGuard::assertExternalUrl('http://127.0.0.1/admin');
        } catch (\InvalidArgumentException) {
            $threw = true;
        }
        $t->assertTrue($threw, 'Should block 127.0.0.1');
    });

    $t->test('SSRF guard blocks private 10.x range', static function () use ($t): void {
        $threw = false;
        try {
            SsrfGuard::assertExternalUrl('http://10.0.0.1/internal');
        } catch (\InvalidArgumentException) {
            $threw = true;
        }
        $t->assertTrue($threw, 'Should block 10.0.0.1');
    });

    $t->test('SSRF guard blocks cloud metadata endpoint', static function () use ($t): void {
        $threw = false;
        try {
            SsrfGuard::assertExternalUrl('http://169.254.169.254/latest/meta-data/');
        } catch (\InvalidArgumentException) {
            $threw = true;
        }
        $t->assertTrue($threw, 'Should block 169.254.169.254');
    });

    $t->test('SSRF guard blocks private 192.168.x range', static function () use ($t): void {
        $threw = false;
        try {
            SsrfGuard::assertExternalUrl('http://192.168.1.1/');
        } catch (\InvalidArgumentException) {
            $threw = true;
        }
        $t->assertTrue($threw, 'Should block 192.168.1.1');
    });

    $t->test('SSRF guard blocks non-HTTP schemes', static function () use ($t): void {
        $threw = false;
        try {
            SsrfGuard::assertExternalUrl('file:///etc/passwd');
        } catch (\InvalidArgumentException) {
            $threw = true;
        }
        $t->assertTrue($threw, 'Should block file:// scheme');
    });

    $t->test('SSRF guard blocks invalid URLs', static function () use ($t): void {
        $threw = false;
        try {
            SsrfGuard::assertExternalUrl('not-a-url');
        } catch (\InvalidArgumentException) {
            $threw = true;
        }
        $t->assertTrue($threw, 'Should block invalid URLs');
    });

    $t->test('SSRF guard allows public IPs', static function () use ($t): void {
        // Use an IP directly to avoid DNS dependency in CI/sandboxed environments
        $threw = false;
        try {
            SsrfGuard::assertExternalUrl('https://93.184.216.34/webhook');
        } catch (\InvalidArgumentException) {
            $threw = true;
        }
        $t->assertTrue(!$threw, 'Should allow public IP addresses');
    });
};
