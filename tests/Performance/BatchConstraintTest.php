<?php

declare(strict_types=1);

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Database\Migrator;
use BacklinkChecker\Domain\Url\LinkClassifier;
use BacklinkChecker\Domain\Url\UrlNormalizer;
use BacklinkChecker\Exceptions\ValidationException;
use BacklinkChecker\Logging\JsonLogger;
use BacklinkChecker\Providers\MetricsProviderInterface;
use BacklinkChecker\Services\BacklinkAnalyzerService;
use BacklinkChecker\Services\HttpClient;
use BacklinkChecker\Services\NotificationService;
use BacklinkChecker\Services\ProviderCacheService;
use BacklinkChecker\Services\QueueService;
use BacklinkChecker\Services\ScanService;
use BacklinkChecker\Services\TelemetryService;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $tmpDb = sys_get_temp_dir() . '/backlink_checker_perf_' . uniqid('', true) . '.sqlite';
    $db = new Database($tmpDb);
    $logger = new JsonLogger(sys_get_temp_dir() . '/backlink_checker_test.log');
    (new Migrator($db, dirname(__DIR__, 2) . '/migrations', $logger))->migrate();

    $db->execute('INSERT INTO users(email, password_hash, display_name, locale, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', ['admin@example.com', 'hash', 'Admin', 'en-US', gmdate('c'), gmdate('c')]);
    $userId = $db->lastInsertId();
    $db->execute('INSERT INTO projects(name, description, root_domain, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', ['Project', '', 'example.com', $userId, gmdate('c'), gmdate('c')]);
    $projectId = $db->lastInsertId();

    $config = new Config([
        'QUEUE_MAX_ATTEMPTS' => 3,
        'SCAN_MAX_CONCURRENCY' => 5,
        'SCAN_TIMEOUT_SECONDS' => 5,
        'SCAN_CONNECT_TIMEOUT_SECONDS' => 5,
        'SCAN_MAX_REDIRECTS' => 2,
        'SCAN_USER_AGENT' => 'TestAgent',
        'TELEMETRY_ENABLED' => false,
        'WEBHOOK_SIGNING_SECRET' => 'secret',
    ]);

    $queue = new QueueService($db, $config);
    $http = new HttpClient($config);
    $normalizer = new UrlNormalizer();
    $analyzer = new BacklinkAnalyzerService($http, $normalizer, new LinkClassifier());

    $provider = new class implements MetricsProviderInterface {
        public function fetchMetrics(array $urls): array { return []; }
        public function healthcheck(): array { return ['ok' => true]; }
    };

    $notifications = new NotificationService($db, $config, $http, $queue);
    $telemetry = new TelemetryService($config, $db);

    $scanService = new ScanService($db, $config, $normalizer, $queue, $analyzer, $provider, $notifications, $telemetry);

    $t->test('scan rejects more than 500 urls', static function () use ($t, $scanService, $projectId, $userId): void {
        $urls = [];
        for ($i = 0; $i < 501; $i++) {
            $urls[] = 'https://example.org/page-' . $i;
        }

        $thrown = false;
        try {
            $scanService->createScan($projectId, $userId, 'example.com', $urls);
        } catch (ValidationException $e) {
            $thrown = true;
        }

        $t->assertTrue($thrown, 'Expected ValidationException for >500 URLs');
    });
};
