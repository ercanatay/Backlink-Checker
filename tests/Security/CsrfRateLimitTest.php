<?php

declare(strict_types=1);

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Database\Migrator;
use BacklinkChecker\Http\RateLimiter;
use BacklinkChecker\Logging\JsonLogger;
use BacklinkChecker\Security\Csrf;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $t->test('csrf token validates', static function () use ($t): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $csrf = new Csrf();
        $token = $csrf->token();
        $t->assertTrue($csrf->validate($token));
        $t->assertTrue(!$csrf->validate('invalid'));
    });

    $t->test('rate limiter blocks after limit', static function () use ($t): void {
        $tmpDb = sys_get_temp_dir() . '/backlink_checker_test_rl_' . uniqid('', true) . '.sqlite';
        $db = new Database($tmpDb);
        $logger = new JsonLogger(sys_get_temp_dir() . '/backlink_checker_test.log');
        (new Migrator($db, dirname(__DIR__, 2) . '/migrations', $logger))->migrate();

        $limiter = new RateLimiter($db);
        $allowed1 = $limiter->hit('test-key', 2, 60);
        $allowed2 = $limiter->hit('test-key', 2, 60);
        $allowed3 = $limiter->hit('test-key', 2, 60);

        $t->assertTrue($allowed1);
        $t->assertTrue($allowed2);
        $t->assertTrue(!$allowed3);
    });
};
