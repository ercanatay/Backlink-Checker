<?php

declare(strict_types=1);

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Services\ExportService;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $t->test('ExportService::isValidExportPath prevents path traversal', static function () use ($t): void {
        $root = sys_get_temp_dir() . '/backlink_checker_test_export_' . uniqid('', true);
        if (!is_dir($root . '/storage/exports')) {
            mkdir($root . '/storage/exports', 0775, true);
        }
        touch($root . '/storage/exports/valid.csv');
        touch($root . '/outside.txt');

        $db = new Database($root . '/db.sqlite'); // Dummy DB

        $config = new Config([
            'EXPORT_ABSOLUTE_PATH' => $root . '/storage/exports',
        ]);

        $service = new ExportService($db, $config);

        // valid case
        $t->assertTrue($service->isValidExportPath($root . '/storage/exports/valid.csv'), 'Valid file should be accepted');

        // traversal attempts
        $t->assertTrue(!$service->isValidExportPath($root . '/storage/exports/../outside.txt'), 'Path traversal should be rejected');
        $t->assertTrue(!$service->isValidExportPath($root . '/outside.txt'), 'Outside file should be rejected');

        // non-existent file
        $t->assertTrue(!$service->isValidExportPath($root . '/storage/exports/nonexistent.csv'), 'Non-existent file should be rejected');

        // cleanup
        unlink($root . '/storage/exports/valid.csv');
        unlink($root . '/outside.txt');
        rmdir($root . '/storage/exports');
        if (file_exists($root . '/db.sqlite')) {
            unlink($root . '/db.sqlite');
        }
        rmdir($root);
    });
};
