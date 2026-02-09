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
    $result = static fn(int $exitCode, string $stdout = '', string $stderr = ''): array => [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];

    $make = static function (callable $runner) use ($result): array {
        $root = sys_get_temp_dir() . '/backlink_checker_updater_integration_' . uniqid('', true);
        @mkdir($root . '/data', 0775, true);
        @mkdir($root . '/storage/logs', 0775, true);
        file_put_contents($root . '/composer.lock', "{}\n");

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
        $fetcher = static fn(string $url): array => [
            'ok' => true,
            'status' => 200,
            'body' => [
                'tag_name' => 'v2.1.0',
                'html_url' => 'https://github.com/ercanatay/Backlink-Checker/releases/tag/v2.1.0',
                'draft' => false,
                'prerelease' => false,
            ],
            'error' => null,
        ];

        $service = new UpdaterService($config, $db, $settings, $queue, $logger, $root, $runner, $fetcher);

        return [$service, $queue, $db];
    };

    $t->test('queue processes updater job types', static function () use ($t): void {
        $tmpDb = sys_get_temp_dir() . '/backlink_checker_updater_queue_' . uniqid('', true) . '.sqlite';
        $db = new Database($tmpDb);
        $logger = new JsonLogger(sys_get_temp_dir() . '/backlink_checker_test.log');
        (new Migrator($db, dirname(__DIR__, 2) . '/migrations', $logger))->migrate();

        $queue = new QueueService($db, new Config(['QUEUE_MAX_ATTEMPTS' => 3]));

        $checkId = $queue->enqueue('updater.check', ['trigger' => 'test']);
        $applyId = $queue->enqueue('updater.apply', ['trigger' => 'test']);

        $first = $queue->reserveNext();
        $t->assertSame('updater.check', (string) ($first['type'] ?? ''));
        $queue->complete((int) $first['id']);

        $second = $queue->reserveNext();
        $t->assertSame('updater.apply', (string) ($second['type'] ?? ''));
        $queue->complete((int) $second['id']);

        $checkRow = $db->fetchOne('SELECT status FROM jobs WHERE id = ?', [$checkId]);
        $applyRow = $db->fetchOne('SELECT status FROM jobs WHERE id = ?', [$applyId]);

        $t->assertSame('completed', (string) ($checkRow['status'] ?? ''));
        $t->assertSame('completed', (string) ($applyRow['status'] ?? ''));
    });

    $t->test('updater apply success marks restart required', static function () use ($t, $make, $result): void {
        $head = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $commands = [];

        $runner = static function (array $command, string $cwd) use (&$head, &$commands, $result): array {
            $commands[] = implode(' ', $command);

            if ($command === ['git', 'rev-parse', 'HEAD']) {
                return $result(0, $head . "\n");
            }
            if ($command === ['git', 'rev-parse', '--abbrev-ref', 'HEAD']) {
                return $result(0, "main\n");
            }
            if ($command === ['git', 'tag', '--points-at', 'HEAD']) {
                return $head === 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
                    ? $result(0, "v2.0.0\n")
                    : $result(0, "v2.1.0\n");
            }
            if ($command === ['git', 'status', '--porcelain']) {
                return $result(0, '');
            }
            if ($command === ['git', 'pull', '--ff-only']) {
                $head = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
                return $result(0, 'Already up to date.');
            }
            if ($command === ['php', 'bin/migrate.php']) {
                return $result(0, 'ok');
            }

            return $result(1, '', 'unknown command: ' . implode(' ', $command));
        };

        [$service] = $make($runner);
        $res = $service->runApply();

        $t->assertTrue($res['ok'] === true, 'apply should succeed');
        $t->assertSame('success', $res['status']);
        $t->assertSame(true, (bool) ($res['state']['restart_required'] ?? false));
        $t->assertSame(false, (bool) ($res['state']['rollback_performed'] ?? true));
        $t->assertTrue(is_string($res['backup_path']) && $res['backup_path'] !== '', 'backup path should be returned');
        $t->assertTrue(file_exists((string) $res['backup_path']), 'backup file should exist');
        $t->assertTrue(!in_array('composer install --no-interaction --no-progress', $commands, true), 'composer install should be skipped when lock is unchanged');
    });

    $t->test('updater apply rollback when migrate fails', static function () use ($t, $make, $result): void {
        $head = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $resetCalled = false;

        $runner = static function (array $command, string $cwd) use (&$head, &$resetCalled, $result): array {
            if ($command === ['git', 'rev-parse', 'HEAD']) {
                return $result(0, $head . "\n");
            }
            if ($command === ['git', 'rev-parse', '--abbrev-ref', 'HEAD']) {
                return $result(0, "main\n");
            }
            if ($command === ['git', 'tag', '--points-at', 'HEAD']) {
                return $result(0, "v2.0.0\n");
            }
            if ($command === ['git', 'status', '--porcelain']) {
                return $result(0, '');
            }
            if ($command === ['git', 'pull', '--ff-only']) {
                $head = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
                return $result(0, 'pulled');
            }
            if ($command === ['php', 'bin/migrate.php']) {
                return $result(1, '', 'migration_failed');
            }
            if ($command === ['git', 'reset', '--hard', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']) {
                $resetCalled = true;
                $head = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
                return $result(0, 'reset');
            }

            return $result(1, '', 'unknown command: ' . implode(' ', $command));
        };

        [$service] = $make($runner);
        $res = $service->runApply();

        $t->assertTrue($res['ok'] === false, 'apply should fail and rollback');
        $t->assertSame('rolled_back', $res['status']);
        $t->assertSame(true, (bool) ($res['state']['rollback_performed'] ?? false));
        $t->assertTrue($resetCalled, 'git reset --hard should be called');
    });
};
