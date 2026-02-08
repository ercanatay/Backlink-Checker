<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;

final class AuditService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(?int $userId, string $action, ?string $targetType, ?string $targetId, array $metadata = [], ?string $ip = null, ?string $userAgent = null): void
    {
        $this->db->execute(
            'INSERT INTO audit_logs(user_id, action, target_type, target_id, metadata_json, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $action, $targetType, $targetId, json_encode($metadata), $ip, $userAgent, gmdate('c')]
        );
    }
}
