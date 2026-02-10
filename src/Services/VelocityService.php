<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;

final class VelocityService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Calculate velocity by comparing current scan with previous.
     *
     * @return array<string, mixed>
     */
    public function calculate(int $scanId): array
    {
        $scan = $this->db->fetchOne('SELECT * FROM scans WHERE id = ?', [$scanId]);
        if ($scan === null) {
            return ['gained' => 0, 'lost' => 0, 'net' => 0];
        }

        $previous = $this->db->fetchOne(
            'SELECT id FROM scans WHERE project_id = ? AND id < ? AND status = ? ORDER BY id DESC LIMIT 1',
            [$scan['project_id'], $scanId, 'completed']
        );

        if ($previous === null) {
            $currentCount = (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS c FROM scan_results WHERE scan_id = ? AND backlink_found = 1',
                [$scanId]
            )['c'] ?? 0);

            $this->storeSnapshot((int) $scan['project_id'], $scanId, $currentCount, 0);

            return ['gained' => $currentCount, 'lost' => 0, 'net' => $currentCount];
        }

        $currentDomains = $this->db->fetchAll(
            'SELECT DISTINCT source_domain FROM scan_results WHERE scan_id = ? AND backlink_found = 1',
            [$scanId]
        );
        $previousDomains = $this->db->fetchAll(
            'SELECT DISTINCT source_domain FROM scan_results WHERE scan_id = ? AND backlink_found = 1',
            [$previous['id']]
        );

        $current = array_map(static fn(array $r): string => (string) $r['source_domain'], $currentDomains);
        $prev = array_map(static fn(array $r): string => (string) $r['source_domain'], $previousDomains);

        $gained = count(array_diff($current, $prev));
        $lost = count(array_diff($prev, $current));
        $net = $gained - $lost;

        $this->storeSnapshot((int) $scan['project_id'], $scanId, $gained, $lost);

        return [
            'scan_id' => $scanId,
            'previous_scan_id' => (int) $previous['id'],
            'gained' => $gained,
            'lost' => $lost,
            'net' => $net,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProjectHistory(int $projectId, int $limit = 30): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM velocity_snapshots WHERE project_id = ? ORDER BY id DESC LIMIT ?',
            [$projectId, $limit]
        );
    }

    private function storeSnapshot(int $projectId, int $scanId, int $gained, int $lost): void
    {
        $this->db->execute(
            'INSERT INTO velocity_snapshots(project_id, scan_id, gained, lost, net, snapshot_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $scanId, $gained, $lost, $gained - $lost, gmdate('Y-m-d'), gmdate('c')]
        );
    }
}
