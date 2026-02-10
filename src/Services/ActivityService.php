<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;

final class ActivityService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT al.*, u.email AS user_email, u.display_name FROM audit_logs al '
            . 'LEFT JOIN users u ON u.id = al.user_id WHERE 1=1';
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= ' AND al.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $sql .= ' AND al.action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $sql .= ' AND al.created_at >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= ' AND al.created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $sql .= ' ORDER BY al.id DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $total = $this->db->fetchOne('SELECT COUNT(*) AS c FROM audit_logs');
        $today = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM audit_logs WHERE created_at >= ?',
            [gmdate('Y-m-d') . 'T00:00:00+00:00']
        );
        $topUsers = $this->db->fetchAll(
            'SELECT u.display_name, u.email, COUNT(*) AS action_count FROM audit_logs al '
            . 'JOIN users u ON u.id = al.user_id GROUP BY al.user_id ORDER BY action_count DESC LIMIT 5'
        );
        $topActions = $this->db->fetchAll(
            'SELECT action, COUNT(*) AS action_count FROM audit_logs GROUP BY action ORDER BY action_count DESC LIMIT 10'
        );

        return [
            'total' => (int) ($total['c'] ?? 0),
            'today' => (int) ($today['c'] ?? 0),
            'top_users' => $topUsers,
            'top_actions' => $topActions,
        ];
    }

    /**
     * Export activity log as CSV string.
     */
    public function exportCsv(array $filters = []): string
    {
        $rows = $this->list($filters, 10000, 0);
        $output = "Date,User,Action,Target Type,Target ID,IP Address\n";

        foreach ($rows as $row) {
            $output .= implode(',', [
                '"' . ($row['created_at'] ?? '') . '"',
                '"' . ($row['user_email'] ?? '') . '"',
                '"' . ($row['action'] ?? '') . '"',
                '"' . ($row['target_type'] ?? '') . '"',
                '"' . ($row['target_id'] ?? '') . '"',
                '"' . ($row['ip_address'] ?? '') . '"',
            ]) . "\n";
        }

        return $output;
    }
}
