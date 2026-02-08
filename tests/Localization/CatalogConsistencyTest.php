<?php

declare(strict_types=1);

use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $langDir = dirname(__DIR__, 2) . '/resources/lang';
    $files = glob($langDir . '/*.json') ?: [];

    $t->test('ten locale files exist', static function () use ($t, $files): void {
        $t->assertSame(10, count($files));
    });

    $t->test('all locale catalogs share same keys', static function () use ($t, $files): void {
        $base = null;
        foreach ($files as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            $t->assertTrue(is_array($decoded), 'Invalid JSON in ' . basename($file));

            unset($decoded['_meta']);
            $keys = array_keys($decoded);
            sort($keys);

            if ($base === null) {
                $base = $keys;
                continue;
            }

            $t->assertSame($base, $keys, 'Key mismatch in ' . basename($file));
        }
    });

    $t->test('ar-SA is marked as rtl', static function () use ($t, $langDir): void {
        $decoded = json_decode((string) file_get_contents($langDir . '/ar-SA.json'), true);
        $t->assertTrue((bool) ($decoded['_meta']['rtl'] ?? false));
    });
};
