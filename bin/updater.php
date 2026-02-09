#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

$app = backlink_checker_app();
$updater = $app->updater();
$command = strtolower((string) ($argv[1] ?? 'status'));

switch ($command) {
    case 'status':
        echo json_encode($updater->state(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);

    case 'check':
        $result = $updater->runCheck();
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(($result['ok'] ?? false) ? 0 : 1);

    case 'apply':
        $result = $updater->runApply();
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(($result['ok'] ?? false) ? 0 : 1);

    default:
        fwrite(STDERR, "Usage: php bin/updater.php [status|check|apply]\n");
        exit(2);
}
