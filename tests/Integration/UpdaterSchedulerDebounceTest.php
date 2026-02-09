<?php

declare(strict_types=1);

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Database\Migrator;
use BacklinkChecker\Logging\JsonLogger;
use BacklinkChecker\Services\QueueService;
use BacklinkChecker\Services\SettingsService;
use BacklinkChecker\Services\UpdaterService;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $make = static function (): array {
        $root = sys_get_temp_dir() . '/backlink_checker_updater_scheduler_' . uniqid('', true);
        @mkdir($root . '/data', 0775, true);
        @mkdir($root . '/storage/logs', 0775, true);

        $tmpDb = $root . '/data/app.sqlite';
        $db = new Database($tmpDb);
        $logger = new JsonLogger($root . '/storage/logs/test.log');
        (new Migrator($db, dirname(__DIR__, 2) . '/migrations', $logger))->migrate();

        $config = new Config([
            'DB_ABSOLUTE_PATH' => $tmpDb,
            'QUEUE_MAX_ATTEMPTS' => 3,
        ]);

        $settings = new SettingsService($db);
        $queue = new QueueService($db, $config);

        $runner = static fn(array $command, string $cwd): array => [
            'exit_code' => 1,
            'stdout' => '',
            'stderr' => 'unexpected command: ' . implode(' ', $command),
        ];

        $fetcher = static fn(string $url): array => ['ok' => false, 'status' => 500, 'body' => [], 'error' => 'unused'];
        $service = new UpdaterService($config, $db, $settings, $queue, $logger, $root, $runner, $fetcher);

        return [$service, $settings, $queue];
    };

    $seedState = static function (SettingsService $settings, string $lastCheckedAt): void {
        $settings->set('updater.state', [
            'current_version' => 'v2.0.0',
            'current_commit' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'current_branch' => 'main',
            'last_checked_at' => $lastCheckedAt,
        ]);
    };

    $t->test('scheduler enqueues updater check when interval elapsed', static function () use ($t, $make, $seedState): void {
        [$service, $settings, $queue] = $make();
        $seedState($settings, gmdate('c', time() - 7200));

        $jobId = $service->enqueuePeriodicCheckIfDue();
        $t->assertTrue($jobId !== null && $jobId > 0, 'check job should be enqueued');

        $job = $queue->reserveNext();
        $t->assertSame('updater.check', (string) ($job['type'] ?? ''));
    });

    $t->test('scheduler skips updater check when interval not elapsed', static function () use ($t, $make, $seedState): void {
        [$service, $settings] = $make();
        $seedState($settings, gmdate('c'));

        $jobId = $service->enqueuePeriodicCheckIfDue();
        $t->assertSame(null, $jobId);
    });

    $t->test('scheduler skips updater check when another updater job is pending', static function () use ($t, $make, $seedState): void {
        [$service, $settings, $queue] = $make();
        $seedState($settings, gmdate('c', time() - 7200));

        $queue->enqueue('updater.apply', ['trigger' => 'manual']);
        $jobId = $service->enqueuePeriodicCheckIfDue();

        $t->assertSame(null, $jobId);
    });
};
