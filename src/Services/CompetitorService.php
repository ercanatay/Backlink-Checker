<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;
use BacklinkChecker\Exceptions\ValidationException;

final class CompetitorService
{
    public function __construct(private readonly Database $db)
    {
    }

    public function addCompetitor(int $projectId, int $userId, string $domain): int
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            throw new ValidationException('Competitor domain is required');
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM competitors WHERE project_id = ? AND domain = ?',
            [$projectId, $domain]
        );
        if ($existing !== null) {
            throw new ValidationException('Competitor already exists');
        }

        $count = $this->db->fetchOne('SELECT COUNT(*) AS c FROM competitors WHERE project_id = ?', [$projectId]);
        if ((int) ($count['c'] ?? 0) >= 20) {
            throw new ValidationException('Maximum 20 competitors per project');
        }

        $this->db->execute(
            'INSERT INTO competitors(project_id, domain, created_by, created_at) VALUES (?, ?, ?, ?)',
            [$projectId, $domain, $userId, gmdate('c')]
        );

        return $this->db->lastInsertId();
    }

    public function removeCompetitor(int $competitorId, int $projectId): void
    {
        $this->db->execute('DELETE FROM competitors WHERE id = ? AND project_id = ?', [$competitorId, $projectId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByProject(int $projectId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM competitors WHERE project_id = ? ORDER BY domain ASC',
            [$projectId]
        );
    }

    /**
     * Compare backlink sources between project scans and competitor domains.
     *
     * @return array<string, mixed>
     */
    public function compareWithLatestScan(int $projectId): array
    {
        $competitors = $this->listByProject($projectId);
        if ($competitors === []) {
            return ['competitors' => [], 'own_domains' => [], 'opportunities' => []];
        }

        $latestScan = $this->db->fetchOne(
            'SELECT id FROM scans WHERE project_id = ? AND status = ? ORDER BY id DESC LIMIT 1',
            [$projectId, 'completed']
        );

        if ($latestScan === null) {
            return ['competitors' => $competitors, 'own_domains' => [], 'opportunities' => []];
        }

        $ownDomains = $this->db->fetchAll(
            'SELECT DISTINCT source_domain FROM scan_results WHERE scan_id = ? AND backlink_found = 1',
            [$latestScan['id']]
        );

        $ownDomainList = array_map(static fn(array $r): string => (string) $r['source_domain'], $ownDomains);

        return [
            'competitors' => $competitors,
            'own_domains' => $ownDomainList,
            'opportunities' => [],
            'scan_id' => (int) $latestScan['id'],
            'own_backlink_count' => count($ownDomainList),
        ];
    }
}
