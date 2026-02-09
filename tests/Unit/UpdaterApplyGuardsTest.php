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
    $makeService = static function (callable $runner): array {
        $root = sys_get_temp_dir() . '/backlink_checker_updater_apply_' . uniqid('', true);
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

        $fetcher = static fn(string $url): array => [
            'ok' => false,
            'status' => 500,
            'body' => [],
            'error' => 'unused',
        ];

        $service = new UpdaterService($config, $db, $settings, $queue, $logger, $root, $runner, $fetcher);

        return [$service, $settings];
    };

    $result = static fn(int $exitCode, string $stdout = '', string $stderr = ''): array => [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];

    $t->test('updater apply fails on dirty repository', static function () use ($t, $makeService, $result): void {
        $runner = static function (array $command, string $cwd) use ($result): array {
            if ($command === ['git', 'rev-parse', 'HEAD']) {
                return $result(0, "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\n");
            }
            if ($command === ['git', 'rev-parse', '--abbrev-ref', 'HEAD']) {
                return $result(0, "main\n");
            }
            if ($command === ['git', 'tag', '--points-at', 'HEAD']) {
                return $result(0, "v2.0.0\n");
            }
            if ($command === ['git', 'status', '--porcelain']) {
                return $result(0, " M README.md\n");
            }

            return $result(1, '', 'unknown command: ' . implode(' ', $command));
        };

        [$service] = $makeService($runner);
        $res = $service->runApply();

        $t->assertTrue($res['ok'] === false, 'apply should fail for dirty repo');
        $t->assertSame('failed', $res['status']);
        $t->assertSame('dirty_repo', (string) ($res['state']['last_apply_error'] ?? ''));
        $t->assertSame(false, (bool) ($res['state']['rollback_performed'] ?? true));
    });

    $t->test('updater apply keeps existing state fields when patching', static function () use ($t, $makeService, $result): void {
        $runner = static function (array $command, string $cwd) use ($result): array {
            if ($command === ['git', 'rev-parse', 'HEAD']) {
                return $result(0, "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\n");
            }
            if ($command === ['git', 'rev-parse', '--abbrev-ref', 'HEAD']) {
                return $result(0, "main\n");
            }
            if ($command === ['git', 'tag', '--points-at', 'HEAD']) {
                return $result(0, "v2.0.0\n");
            }
            if ($command === ['git', 'status', '--porcelain']) {
                return $result(0, " M src/App.php\n");
            }

            return $result(1, '', 'unknown command: ' . implode(' ', $command));
        };

        [$service, $settings] = $makeService($runner);
        $settings->set('updater.state', [
            'latest_version' => 'v9.9.9',
            'latest_url' => 'https://example.com/release',
            'update_available' => true,
        ]);

        $res = $service->runApply();

        $t->assertSame('v9.9.9', (string) ($res['state']['latest_version'] ?? ''));
        $t->assertSame('https://example.com/release', (string) ($res['state']['latest_url'] ?? ''));
        $t->assertSame(true, (bool) ($res['state']['update_available'] ?? false));
    });
};
