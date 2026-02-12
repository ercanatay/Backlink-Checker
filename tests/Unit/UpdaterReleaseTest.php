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
    $makeService = static function (callable $runner, callable $fetcher): array {
        $root = sys_get_temp_dir() . '/backlink_checker_updater_release_' . uniqid('', true);
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

        $service = new UpdaterService($config, $db, $settings, $queue, $logger, $root, $runner, $fetcher);

        return [$service, $settings];
    };

    $result = static fn(int $exitCode, string $stdout = '', string $stderr = ''): array => [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];

    $t->test('updater check marks update_available for newer stable release', static function () use ($t, $makeService, $result): void {
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
            if ($command === ['git', 'fetch', '--tags', '--prune', 'origin']) {
                return $result(0, '');
            }
            if ($command === ['git', 'remote', 'get-url', 'origin']) {
                return $result(0, "https://github.com/ercanatay/cybokron-backlink-checker.git\n");
            }
            if ($command === ['git', 'rev-list', '-n', '1', 'v2.1.0']) {
                return $result(0, "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb\n");
            }
            if ($command[0] === 'git' && $command[1] === 'merge-base') {
                return $result(1, '', '');
            }

            return $result(1, '', 'unknown command: ' . implode(' ', $command));
        };

        $fetcher = static fn(string $url): array => [
            'ok' => true,
            'status' => 200,
            'body' => [
                'tag_name' => 'v2.1.0',
                'html_url' => 'https://github.com/ercanatay/cybokron-backlink-checker/releases/tag/v2.1.0',
                'draft' => false,
                'prerelease' => false,
            ],
            'error' => null,
        ];

        [$service] = $makeService($runner, $fetcher);

        $res = $service->runCheck();

        $t->assertTrue($res['ok'] === true, 'check should succeed');
        $t->assertSame('update_available', $res['status']);
        $t->assertSame(true, (bool) ($res['state']['update_available'] ?? false));
        $t->assertSame('v2.1.0', (string) ($res['state']['latest_version'] ?? ''));
    });

    $t->test('updater check rejects prerelease or draft latest release', static function () use ($t, $makeService, $result): void {
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
            if ($command === ['git', 'fetch', '--tags', '--prune', 'origin']) {
                return $result(0, '');
            }
            if ($command === ['git', 'remote', 'get-url', 'origin']) {
                return $result(0, "https://github.com/ercanatay/cybokron-backlink-checker.git\n");
            }

            return $result(1, '', 'unknown command: ' . implode(' ', $command));
        };

        $fetcher = static fn(string $url): array => [
            'ok' => true,
            'status' => 200,
            'body' => [
                'tag_name' => 'v2.2.0',
                'html_url' => 'https://github.com/ercanatay/cybokron-backlink-checker/releases/tag/v2.2.0',
                'draft' => false,
                'prerelease' => true,
            ],
            'error' => null,
        ];

        [$service] = $makeService($runner, $fetcher);

        $res = $service->runCheck();

        $t->assertTrue($res['ok'] === false, 'check should fail for prerelease');
        $t->assertSame('check_error', $res['status']);
        $t->assertSame('latest_release_is_not_stable', (string) ($res['state']['last_check_error'] ?? ''));
    });

    $t->test('updater config defaults are persisted on first access', static function () use ($t, $makeService, $result): void {
        $runner = static fn(array $command, string $cwd): array => $result(1, '', 'unused');
        $fetcher = static fn(string $url): array => ['ok' => false, 'status' => 500, 'body' => [], 'error' => 'unused'];

        [$service, $settings] = $makeService($runner, $fetcher);
        $config = $service->config();
        $saved = $settings->get('updater.config', []);

        $t->assertSame(true, (bool) ($config['enabled'] ?? false));
        $t->assertSame(60, (int) ($config['interval_minutes'] ?? 0));
        $t->assertSame(60, (int) ($saved['interval_minutes'] ?? 0));
    });
};
