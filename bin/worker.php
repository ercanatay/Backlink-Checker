#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

$app = backlink_checker_app();
$queue = $app->queue();
$db = $app->db();
$logger = $app->logger();

$iterations = (int) ($argv[1] ?? 0);
$count = 0;
$idleLoops = 0;

while (true) {
    $job = $queue->reserveNext();
    if ($job === null) {
        if ($iterations > 0) {
            $idleLoops++;
            if ($count >= $iterations || $idleLoops >= 3) {
                break;
            }
        }

        if ($iterations > 0 && $count >= $iterations) {
            break;
        }
        sleep(2);
        continue;
    }

    $idleLoops = 0;
    $count++;
    $payload = json_decode((string) $job['payload_json'], true);
    if (!is_array($payload)) {
        $payload = [];
    }

    try {
        if ((string) $job['type'] === 'scan.run') {
            $scanId = (int) ($payload['scan_id'] ?? 0);
            if ($scanId > 0) {
                $app->scans()->processScan($scanId);
            }
            $queue->complete((int) $job['id']);
            $logger->info('worker.scan.completed', ['job_id' => (int) $job['id'], 'scan_id' => $scanId]);
            continue;
        }

        if ((string) $job['type'] === 'webhook.deliver') {
            $notificationId = (int) ($payload['notification_id'] ?? 0);
            $attempt = (int) ($payload['attempt'] ?? 1);

            $notification = $db->fetchOne('SELECT * FROM notifications WHERE id = ?', [$notificationId]);
            if ($notification === null) {
                $queue->complete((int) $job['id']);
                continue;
            }

            $result = $app->webhooks()->deliver($notification, is_array($payload['payload'] ?? null) ? $payload['payload'] : [], $attempt);
            if ($result['success']) {
                $queue->complete((int) $job['id']);
            } else {
                $queue->fail((int) $job['id'], 'Webhook delivery failed status=' . $result['status_code']);
            }
            continue;
        }

        if ((string) $job['type'] === 'updater.check') {
            $result = $app->updater()->runCheck();
            $queue->complete((int) $job['id']);
            $logger->info('worker.updater.check.completed', [
                'job_id' => (int) $job['id'],
                'status' => (string) ($result['status'] ?? 'unknown'),
                'ok' => (bool) ($result['ok'] ?? false),
            ]);
            continue;
        }

        if ((string) $job['type'] === 'updater.apply') {
            $result = $app->updater()->runApply();
            $queue->complete((int) $job['id']);
            $logger->info('worker.updater.apply.completed', [
                'job_id' => (int) $job['id'],
                'status' => (string) ($result['status'] ?? 'unknown'),
                'ok' => (bool) ($result['ok'] ?? false),
            ]);
            continue;
        }

        $queue->complete((int) $job['id']);
    } catch (Throwable $e) {
        $queue->fail((int) $job['id'], $e->getMessage());
        $logger->error('worker.job.failed', ['job_id' => (int) $job['id'], 'error' => $e->getMessage()]);
    }

    if ($iterations > 0 && $count >= $iterations) {
        break;
    }
}

echo "Worker finished after {$count} jobs.\n";
