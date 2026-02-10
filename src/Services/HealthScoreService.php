<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;

final class HealthScoreService
{
    private const WEIGHT_BACKLINK_RATIO = 30;
    private const WEIGHT_DOFOLLOW_RATIO = 25;
    private const WEIGHT_AVG_DA = 25;
    private const WEIGHT_TOXIC = 20;
    private const TOXIC_DA_THRESHOLD = 10.0;

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Calculate and store a health score for a completed scan.
     *
     * @return array<string, mixed>
     */
    public function calculate(int $scanId): array
    {
        $scan = $this->db->fetchOne('SELECT * FROM scans WHERE id = ?', [$scanId]);
        if ($scan === null) {
            return ['overall_score' => 0];
        }

        $agg = $this->db->fetchOne(
            'SELECT COUNT(*) AS total, '
            . 'SUM(backlink_found) AS backlinks, '
            . 'SUM(CASE WHEN best_link_type = ? THEN 1 ELSE 0 END) AS dofollow_count, '
            . 'AVG(CASE WHEN domain_authority IS NOT NULL THEN domain_authority ELSE NULL END) AS avg_da, '
            . 'SUM(CASE WHEN domain_authority IS NOT NULL AND domain_authority < ? THEN 1 ELSE 0 END) AS toxic_count '
            . 'FROM scan_results WHERE scan_id = ?',
            ['dofollow', self::TOXIC_DA_THRESHOLD, $scanId]
        );

        $total = max(1, (int) ($agg['total'] ?? 1));
        $backlinks = (int) ($agg['backlinks'] ?? 0);
        $dofollowCount = (int) ($agg['dofollow_count'] ?? 0);
        $avgDa = (float) ($agg['avg_da'] ?? 0);
        $toxicCount = (int) ($agg['toxic_count'] ?? 0);

        $backlinkRatio = $backlinks / $total;
        $dofollowRatio = $backlinks > 0 ? $dofollowCount / $backlinks : 0;
        $daScore = min($avgDa / 100, 1.0);
        $toxicRatio = 1 - min($toxicCount / max($total, 1), 1.0);

        $overall = round(
            ($backlinkRatio * self::WEIGHT_BACKLINK_RATIO
            + $dofollowRatio * self::WEIGHT_DOFOLLOW_RATIO
            + $daScore * self::WEIGHT_AVG_DA
            + $toxicRatio * self::WEIGHT_TOXIC) ,
            2
        );

        $this->db->execute(
            'INSERT INTO health_scores(scan_id, project_id, overall_score, backlink_ratio, dofollow_ratio, avg_da, toxic_count, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?) '
            . 'ON CONFLICT(scan_id) DO UPDATE SET overall_score = excluded.overall_score, '
            . 'backlink_ratio = excluded.backlink_ratio, dofollow_ratio = excluded.dofollow_ratio, '
            . 'avg_da = excluded.avg_da, toxic_count = excluded.toxic_count',
            [
                $scanId,
                (int) $scan['project_id'],
                $overall,
                round($backlinkRatio, 4),
                round($dofollowRatio, 4),
                round($avgDa, 2),
                $toxicCount,
                gmdate('c'),
            ]
        );

        return [
            'scan_id' => $scanId,
            'overall_score' => $overall,
            'backlink_ratio' => round($backlinkRatio * 100, 1),
            'dofollow_ratio' => round($dofollowRatio * 100, 1),
            'avg_da' => round($avgDa, 2),
            'toxic_count' => $toxicCount,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getForScan(int $scanId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM health_scores WHERE scan_id = ?', [$scanId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function trendForProject(int $projectId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            'SELECT hs.*, s.created_at AS scan_date FROM health_scores hs '
            . 'JOIN scans s ON s.id = hs.scan_id '
            . 'WHERE hs.project_id = ? ORDER BY s.id DESC LIMIT ?',
            [$projectId, $limit]
        );
    }
}
