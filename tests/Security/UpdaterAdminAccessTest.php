<?php

declare(strict_types=1);

use BacklinkChecker\App;
use BacklinkChecker\Http\Request;
use BacklinkChecker\Security\Csrf;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $makeApp = static function (): array {
        $tmpDir = sys_get_temp_dir() . '/backlink_checker_updater_security_' . uniqid('', true);
        @mkdir($tmpDir . '/storage/logs', 0775, true);

        $dbFile = $tmpDir . '/app.sqlite';
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DB_PATH'] = $dbFile;
        $_ENV['LOG_PATH'] = $tmpDir . '/storage/logs/app.log';
        $_ENV['RATE_LIMIT_LOGIN_PER_15_MIN'] = '100';
        $_ENV['RATE_LIMIT_API_PER_MIN'] = '1000';

        $app = new App(dirname(__DIR__, 2));

        return [$app, $tmpDir, $dbFile];
    };

    $cleanup = static function (string $tmpDir): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        @rmdir($tmpDir);
    };

    $t->test('non-admin cannot trigger updater check route', static function () use ($t, $makeApp, $cleanup): void {
        error_reporting(E_ALL & ~E_WARNING);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }

        [$app, $tmpDir] = $makeApp();

        $_SESSION['user'] = [
            'id' => 1,
            'email' => 'viewer@example.com',
            'display_name' => 'Viewer',
            'locale' => 'en-US',
            'roles' => ['viewer'],
        ];

        $token = (new Csrf())->token();

        $request = new Request(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/settings/updater/check', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost'],
            [],
            ['_csrf' => $token],
            [],
            ''
        );

        ob_start();
        $app->run($request);
        $output = (string) ob_get_clean();

        $app = null;
        $cleanup($tmpDir);

        $t->assertTrue(str_contains($output, 'You do not have permission'), 'viewer should receive forbidden response');
    });

    $t->test('updater check route enforces csrf for admin', static function () use ($t, $makeApp, $cleanup): void {
        error_reporting(E_ALL & ~E_WARNING);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }

        [$app, $tmpDir] = $makeApp();

        $_SESSION['user'] = [
            'id' => 1,
            'email' => 'admin@example.com',
            'display_name' => 'Admin',
            'locale' => 'en-US',
            'roles' => ['admin'],
        ];

        $request = new Request(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/settings/updater/check', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost'],
            [],
            ['_csrf' => 'invalid-token'],
            [],
            ''
        );

        ob_start();
        $app->run($request);
        $output = (string) ob_get_clean();

        $app = null;
        $cleanup($tmpDir);

        $t->assertTrue(str_contains($output, 'Security token mismatch'), 'invalid csrf token should be rejected');
    });
};
