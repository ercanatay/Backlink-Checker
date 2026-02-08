<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;

final class SavedViewService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId, int $projectId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM saved_views WHERE user_id = ? AND project_id = ? ORDER BY updated_at DESC',
            [$userId, $projectId]
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function save(int $userId, int $projectId, string $name, array $filters): int
    {
        $now = gmdate('c');
        $this->db->execute(
            'INSERT INTO saved_views(user_id, project_id, name, filters_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $projectId, trim($name), json_encode($filters), $now, $now]
        );

        return $this->db->lastInsertId();
    }
}
