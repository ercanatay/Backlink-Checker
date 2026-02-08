#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

$app = backlink_checker_app();
$db = $app->db();
$schedules = $app->schedules()->dueSchedules();

$processed = 0;
foreach ($schedules as $schedule) {
    $targets = json_decode((string) $schedule['targets_json'], true);
    if (!is_array($targets)) {
        $targets = [];
    }

    try {
        $scanId = $app->scans()->createScan(
            (int) $schedule['project_id'],
            (int) $schedule['created_by'],
            (string) $schedule['root_domain'],
            array_map(static fn($v): string => (string) $v, $targets),
            'moz'
        );

        $app->schedules()->markRun((int) $schedule['id'], $scanId, 'scheduled');
        $processed++;
    } catch (Throwable $e) {
        $app->schedules()->markRun((int) $schedule['id'], 0, 'failed', $e->getMessage());
    }
}

echo "Scheduler processed {$processed} schedule(s).\n";
