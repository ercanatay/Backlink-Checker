<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;

final class RetentionService
{
    public function __construct(
        private readonly Database $db,
        private readonly Config $config
    ) {
    }

    public function cleanup(): array
    {
        $days = max(1, $this->config->int('RETENTION_DAYS', 90));
        $threshold = gmdate('c', time() - ($days * 86400));

        $tables = [
            'telemetry_events' => 'created_at',
            'audit_logs' => 'created_at',
            'webhook_deliveries' => 'created_at',
            'schedule_runs' => 'created_at',
            'jobs' => 'created_at',
        ];

        $deleted = [];
        foreach ($tables as $table => $column) {
            $before = $this->db->fetchOne('SELECT COUNT(*) AS c FROM ' . $table . ' WHERE ' . $column . ' < ?', [$threshold]);
            $count = (int) ($before['c'] ?? 0);
            $this->db->execute('DELETE FROM ' . $table . ' WHERE ' . $column . ' < ?', [$threshold]);
            $deleted[$table] = $count;
        }

        return ['retention_days' => $days, 'threshold' => $threshold, 'deleted' => $deleted];
    }
}
