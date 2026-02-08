<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;

final class TelemetryService
{
    public function __construct(
        private readonly Config $config,
        private readonly Database $db
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function track(string $eventName, array $payload = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->db->execute(
            'INSERT INTO telemetry_events(event_name, event_payload_json, created_at) VALUES (?, ?, ?)',
            [$eventName, json_encode($payload), gmdate('c')]
        );
    }

    public function isEnabled(): bool
    {
        $row = $this->db->fetchOne('SELECT value_json FROM settings WHERE key = ?', ['telemetry.enabled']);
        if ($row !== null) {
            $decoded = json_decode((string) $row['value_json'], true);
            return (bool) ($decoded['enabled'] ?? false);
        }

        return $this->config->bool('TELEMETRY_ENABLED', false);
    }
}
