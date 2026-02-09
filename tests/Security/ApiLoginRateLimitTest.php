<?php

declare(strict_types=1);

use BacklinkChecker\App;
use BacklinkChecker\Http\Request;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $t->test('api login rate limit blocks excessive attempts', static function () use ($t): void {
        // Suppress header warnings
        error_reporting(E_ALL & ~E_WARNING);

        // Setup temporary directory for DB
        $tempDir = sys_get_temp_dir() . '/backlink_test_' . uniqid();
        if (!mkdir($tempDir) && !is_dir($tempDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }
        $dbFile = $tempDir . '/app.sqlite';

        // Configure environment
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DB_PATH'] = $dbFile;
        $_ENV['RATE_LIMIT_LOGIN_PER_15_MIN'] = '2';
        $_ENV['RATE_LIMIT_API_PER_MIN'] = '100';
        $_ENV['LOG_PATH'] = $tempDir . '/app.log';

        // Instantiate App with real root path
        $rootPath = dirname(__DIR__, 2);
        $app = new App($rootPath);

        // EnvLoader reads .env and can override values above; detect effective limit from .env when present.
        $effectiveLoginLimit = 2;
        $envFile = $rootPath . '/.env';
        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                if (preg_match('/^\\s*RATE_LIMIT_LOGIN_PER_15_MIN\\s*=\\s*(.+)\\s*$/', $line, $m) === 1) {
                    $raw = trim($m[1]);
                    $raw = trim($raw, "\"'");
                    $parsed = (int) $raw;
                    if ($parsed > 0) {
                        $effectiveLoginLimit = $parsed;
                    }
                    break;
                }
            }
        }

        // Buffer output to avoid sending headers prematurely
        ob_start();

        $blocked = false;
        $attempts = $effectiveLoginLimit + 5;

        for ($i = 1; $i <= $attempts; $i++) {
            // Mock request
            $request = new Request(
                ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/v1/auth/login', 'REMOTE_ADDR' => '127.0.0.1', 'CONTENT_TYPE' => 'application/json', 'HTTP_HOST' => 'localhost'],
                [],
                [],
                [],
                json_encode(['email' => 'admin@example.com', 'password' => 'wrong'])
            );

            // Capture output of this run
            ob_start();
            $app->run($request);
            $output = ob_get_clean();

            // Check for rate limit error in JSON
            if (str_contains($output, '"code":"rate_limited"') || str_contains($output, 'Too many login attempts')) {
                $blocked = true;
                break;
            }
        }

        ob_end_clean(); // Discard the outer buffer

        // Clean up
        if (file_exists($dbFile)) unlink($dbFile);
        if (file_exists($tempDir . '/app.log')) unlink($tempDir . '/app.log');
        rmdir($tempDir);

        $t->assertTrue($blocked, 'Requests should be blocked after limit is exceeded');
        $t->assertTrue($i <= ($effectiveLoginLimit + 1), 'Blocking should happen at or before limit+1 attempt');
    });
};
