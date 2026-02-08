<?php

declare(strict_types=1);

use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $t->test('legacy index.php delegates to app bootstrap', static function () use ($t): void {
        $code = (string) file_get_contents(dirname(__DIR__, 2) . '/index.php');
        $t->assertTrue(str_contains($code, 'bootstrap/app.php'));
        $t->assertTrue(str_contains($code, 'backlink_checker_app()->run()'));
    });

    $t->test('public entrypoint exists', static function () use ($t): void {
        $t->assertTrue(is_file(dirname(__DIR__, 2) . '/public/index.php'));
    });
};
