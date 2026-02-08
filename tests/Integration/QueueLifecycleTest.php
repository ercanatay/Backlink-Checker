<?php

declare(strict_types=1);

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Database\Migrator;
use BacklinkChecker\Logging\JsonLogger;
use BacklinkChecker\Services\QueueService;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $tmpDb = sys_get_temp_dir() . '/backlink_checker_test_queue_' . uniqid('', true) . '.sqlite';
    $db = new Database($tmpDb);
    $logger = new JsonLogger(sys_get_temp_dir() . '/backlink_checker_test.log');
    $migrator = new Migrator($db, dirname(__DIR__, 2) . '/migrations', $logger);
    $migrator->migrate();

    $config = new Config([
        'QUEUE_MAX_ATTEMPTS' => 3,
    ]);

    $queue = new QueueService($db, $config);

    $t->test('queue enqueue and reserve', static function () use ($t, $queue): void {
        $id = $queue->enqueue('scan.run', ['scan_id' => 99]);
        $t->assertTrue($id > 0, 'enqueue should return job id');

        $job = $queue->reserveNext();
        $t->assertTrue(is_array($job), 'job should be reserved');
        $t->assertSame('running', $job['status']);
    });

    $t->test('queue fail transitions to queued then dead', static function () use ($t, $queue, $db): void {
        $jobId = $queue->enqueue('scan.run', ['scan_id' => 1]);
        $job = $queue->reserveNext();
        $queue->fail((int) $job['id'], 'first fail');

        $row = $db->fetchOne('SELECT status, attempts FROM jobs WHERE id = ?', [$jobId]);
        $t->assertSame('queued', $row['status']);
        $t->assertSame(1, (int) $row['attempts']);

        // Force dead state
        $db->execute('UPDATE jobs SET attempts = 2, status = ? WHERE id = ?', ['running', $jobId]);
        $queue->fail($jobId, 'final fail');
        $dead = $db->fetchOne('SELECT status FROM jobs WHERE id = ?', [$jobId]);
        $t->assertSame('dead', $dead['status']);
    });
};
