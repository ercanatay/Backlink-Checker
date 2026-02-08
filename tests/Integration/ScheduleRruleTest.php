<?php

declare(strict_types=1);

use BacklinkChecker\Database\Database;
use BacklinkChecker\Database\Migrator;
use BacklinkChecker\Logging\JsonLogger;
use BacklinkChecker\Services\ScheduleService;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $tmpDb = sys_get_temp_dir() . '/backlink_checker_test_schedule_' . uniqid('', true) . '.sqlite';
    $db = new Database($tmpDb);
    $logger = new JsonLogger(sys_get_temp_dir() . '/backlink_checker_test.log');
    (new Migrator($db, dirname(__DIR__, 2) . '/migrations', $logger))->migrate();

    // Seed user/project
    $db->execute('INSERT INTO users(email, password_hash, display_name, locale, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
        ['u@example.com', 'hash', 'User', 'en-US', gmdate('c'), gmdate('c')]);
    $userId = $db->lastInsertId();
    $db->execute('INSERT INTO projects(name, description, root_domain, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
        ['P', '', 'example.com', $userId, gmdate('c'), gmdate('c')]);
    $projectId = $db->lastInsertId();

    $service = new ScheduleService($db);

    $t->test('hourly rrule schedule creation', static function () use ($t, $service, $projectId, $userId): void {
        $id = $service->create($projectId, $userId, 'Hourly', 'example.com', ['https://example.org'], 'FREQ=HOURLY;INTERVAL=2', 'UTC');
        $t->assertTrue($id > 0);
    });

    $t->test('weekly rrule schedule creation', static function () use ($t, $service, $projectId, $userId): void {
        $id = $service->create($projectId, $userId, 'Weekly', 'example.com', ['https://example.org'], 'FREQ=WEEKLY;BYDAY=MO,WE;BYHOUR=9;BYMINUTE=0', 'UTC');
        $t->assertTrue($id > 0);
    });
};
