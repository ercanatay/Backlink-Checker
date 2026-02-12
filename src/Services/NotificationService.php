<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Security\SsrfGuard;

final class NotificationService
{
    public function __construct(
        private readonly Database $db,
        private readonly Config $config,
        private readonly HttpClient $http,
        private readonly QueueService $queue
    ) {
    }

    /**
     * @param array<string, mixed> $scan
     */
    public function notifyScanCompleted(array $scan): void
    {
        $notifications = $this->db->fetchAll(
            'SELECT * FROM notifications WHERE project_id = ? AND event_type = ? AND is_active = 1',
            [$scan['project_id'], 'scan.completed']
        );

        if ($notifications === []) {
            return;
        }

        $payload = [
            'event' => 'scan.completed',
            'timestamp' => gmdate('c'),
            'scan' => [
                'id' => (int) $scan['id'],
                'project_id' => (int) $scan['project_id'],
                'status' => (string) $scan['status'],
                'processed_targets' => (int) $scan['processed_targets'],
                'total_targets' => (int) $scan['total_targets'],
                'finished_at' => (string) ($scan['finished_at'] ?? ''),
            ],
        ];

        foreach ($notifications as $notification) {
            $channel = strtolower((string) $notification['channel']);
            if ($channel === 'email') {
                $this->sendEmail((string) $notification['destination'], $payload);
                continue;
            }

            if ($channel === 'slack') {
                $this->sendSlack((string) $notification['destination'], $payload);
                continue;
            }

            if ($channel === 'webhook') {
                $this->queue->enqueue('webhook.deliver', [
                    'notification_id' => (int) $notification['id'],
                    'payload' => $payload,
                    'attempt' => 1,
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendEmail(string $destination, array $payload): void
    {
        // Prevent email header injection by stripping newlines from destination and From header
        $destination = preg_replace('/[\r\n]/', '', $destination);
        if (!filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $from = preg_replace('/[\r\n]/', '', $this->config->string('SMTP_FROM', 'noreply@example.com'));

        $subject = '[Cybokron Backlink Checker] Scan completed #' . ($payload['scan']['id'] ?? '');
        $body = "Scan #" . ($payload['scan']['id'] ?? '') . " completed with status: " . ($payload['scan']['status'] ?? '')
            . "\nProcessed: " . ($payload['scan']['processed_targets'] ?? 0) . "/" . ($payload['scan']['total_targets'] ?? 0)
            . "\nFinished: " . ($payload['scan']['finished_at'] ?? '');

        if (function_exists('mail')) {
            @mail($destination, $subject, $body, 'From: ' . $from);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendSlack(string $destination, array $payload): void
    {
        $url = trim($destination) !== '' ? $destination : $this->config->string('SLACK_WEBHOOK_URL');
        if ($url === '') {
            return;
        }

        try {
            SsrfGuard::assertExternalUrl($url);
        } catch (\InvalidArgumentException) {
            return;
        }

        $text = sprintf(
            "Scan #%d completed (%s) - %d/%d targets",
            (int) ($payload['scan']['id'] ?? 0),
            (string) ($payload['scan']['status'] ?? 'unknown'),
            (int) ($payload['scan']['processed_targets'] ?? 0),
            (int) ($payload['scan']['total_targets'] ?? 0)
        );

        $this->http->postJson($url, ['text' => $text]);
    }
}
