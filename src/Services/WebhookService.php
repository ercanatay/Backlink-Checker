<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;

final class WebhookService
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly Database $db,
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, mixed> $notification
     * @param array<string, mixed> $payload
     * @return array{success: bool, status_code: int, response: string}
     */
    public function deliver(array $notification, array $payload, int $attempt = 1): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $secret = (string) ($notification['secret'] ?: $this->config->string('WEBHOOK_SIGNING_SECRET'));
        $signature = hash_hmac('sha256', $body, $secret);

        $response = $this->http->postJson(
            (string) $notification['destination'],
            $payload,
            [
                'X-Backlink-Signature' => $signature,
                'X-Backlink-Event' => 'scan.completed',
                'X-Backlink-Attempt' => (string) $attempt,
            ]
        );

        $success = ($response['status'] ?? 0) >= 200 && ($response['status'] ?? 0) < 300;

        if ((int) ($notification['id'] ?? 0) > 0) {
            $this->db->execute(
                'INSERT INTO webhook_deliveries(notification_id, scan_id, endpoint, payload_json, status_code, attempt, success, response_excerpt, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $notification['id'],
                    $payload['scan']['id'] ?? null,
                    $notification['destination'],
                    $body,
                    (int) ($response['status'] ?? 0),
                    $attempt,
                    $success ? 1 : 0,
                    substr((string) ($response['body'] ?? $response['error'] ?? ''), 0, 1000),
                    gmdate('c'),
                ]
            );
        }

        return [
            'success' => $success,
            'status_code' => (int) ($response['status'] ?? 0),
            'response' => (string) ($response['body'] ?? ''),
        ];
    }
}
