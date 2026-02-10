<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Domain\Enum\ScanStatus;
use BacklinkChecker\Domain\Url\UrlNormalizer;
use BacklinkChecker\Exceptions\ValidationException;
use BacklinkChecker\Providers\MetricsProviderInterface;
use Throwable;

final class ScanService
{
    public function __construct(
        private readonly Database $db,
        private readonly Config $config,
        private readonly UrlNormalizer $normalizer,
        private readonly QueueService $queue,
        private readonly BacklinkAnalyzerService $analyzer,
        private readonly MetricsProviderInterface $provider,
        private readonly NotificationService $notifications,
        private readonly TelemetryService $telemetry
    ) {
    }

    /**
     * @param array<int, string> $rawUrls
     */
    public function createScan(int $projectId, int $userId, string $rootDomain, array $rawUrls, string $provider = 'moz'): int
    {
        $normalizedRootDomain = $this->normalizer->rootDomain($rootDomain);
        if ($normalizedRootDomain === '') {
            throw new ValidationException('Invalid root domain');
        }

        $urls = [];
        foreach ($rawUrls as $rawUrl) {
            $normalized = $this->normalizer->normalizeUrl($rawUrl);
            if ($normalized !== '') {
                $urls[$normalized] = $normalized;
            }
        }

        if ($urls === []) {
            throw new ValidationException('At least one valid URL is required');
        }

        $total = count($urls);
        if ($total > 500) {
            throw new ValidationException('Maximum 500 URLs per scan');
        }

        $correlationId = \BacklinkChecker\Support\Uuid::v4();

        $scanId = $this->db->transaction(function () use ($projectId, $userId, $normalizedRootDomain, $provider, $urls, $total, $correlationId): int {
            $now = gmdate('c');
            $this->db->execute(
                'INSERT INTO scans(project_id, requested_by, status, provider, root_domain, total_targets, correlation_id, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$projectId, $userId, ScanStatus::QUEUED, $provider, $normalizedRootDomain, $total, $correlationId, $now, $now]
            );
            $scanId = $this->db->lastInsertId();

            foreach ($urls as $url) {
                $this->db->execute(
                    'INSERT INTO scan_targets(scan_id, url, normalized_url, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
                    [$scanId, $url, $url, 'queued', $now, $now]
                );
            }

            $this->queue->enqueue('scan.run', ['scan_id' => $scanId], null, $correlationId);

            return $scanId;
        });

        $this->telemetry->track('scan.created', ['scan_id' => $scanId, 'project_id' => $projectId]);

        return $scanId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listScansByProject(int $projectId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM scans WHERE project_id = ? ORDER BY id DESC LIMIT ?',
            [$projectId, $limit]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findScan(int $scanId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM scans WHERE id = ?', [$scanId]);
    }

    public function cancelScan(int $scanId): void
    {
        $scan = $this->findScan($scanId);
        if ($scan === null) {
            return;
        }

        if (in_array((string) $scan['status'], [ScanStatus::COMPLETED, ScanStatus::FAILED, ScanStatus::CANCELLED], true)) {
            return;
        }

        $this->db->execute(
            'UPDATE scans SET status = ?, updated_at = ?, finished_at = ? WHERE id = ?',
            [ScanStatus::CANCELLED, gmdate('c'), gmdate('c'), $scanId]
        );
    }

    public function processScan(int $scanId): void
    {
        $scan = $this->findScan($scanId);
        if ($scan === null) {
            return;
        }

        if ((string) $scan['status'] === ScanStatus::CANCELLED) {
            return;
        }

        $this->db->execute(
            'UPDATE scans SET status = ?, started_at = ?, updated_at = ? WHERE id = ?',
            [ScanStatus::RUNNING, gmdate('c'), gmdate('c'), $scanId]
        );

        $targets = $this->db->fetchAll('SELECT * FROM scan_targets WHERE scan_id = ? ORDER BY id ASC', [$scanId]);
        $chunkSize = max(1, $this->config->int('SCAN_MAX_CONCURRENCY', 5));
        $processed = 0;

        try {
            foreach (array_chunk($targets, $chunkSize) as $chunk) {
                // Check cancellation once per chunk instead of per-target (avoids N+1 queries)
                $freshScan = $this->findScan($scanId);
                if ($freshScan !== null && (string) $freshScan['status'] === ScanStatus::CANCELLED) {
                    return;
                }

                $analysisResults = [];
                $metricUrls = [];

                foreach ($chunk as $target) {
                    $analysis = $this->analyzer->analyze((string) $target['url'], (string) $scan['root_domain']);
                    $analysisResults[] = ['target' => $target, 'analysis' => $analysis];

                    if ((string) $analysis['fetch_status'] === 'ok') {
                        $metricUrls[] = (string) $analysis['final_url'];
                    }
                }

                $metrics = $metricUrls === [] ? [] : $this->provider->fetchMetrics($metricUrls);

                // Perf: Batch all DB writes for this chunk in a single transaction.
                // Without this, each INSERT/UPDATE auto-commits individually, requiring
                // one fsync per statement. For a 500-target scan averaging 10 links each,
                // this reduces ~6,500 implicit transactions to ~100 (one per chunk).
                $this->db->beginTransaction();
                try {
                    foreach ($analysisResults as $entry) {
                        $target = $entry['target'];
                        $analysis = $entry['analysis'];

                        $metric = $metrics[(string) $analysis['final_url']] ?? ['pa' => null, 'da' => null, 'status' => 'n/a', 'error' => null];
                        $providerStatus = (string) ($metric['status'] ?? 'n/a');
                        $errorMessage = trim(((string) ($analysis['error_message'] ?? '')) . ' ' . ((string) ($metric['error'] ?? '')));
                        $errorMessage = $errorMessage === '' ? null : $errorMessage;

                        $this->db->execute(
                            'INSERT INTO scan_results(scan_id, target_id, source_url, source_domain, final_url, final_domain, http_status, fetch_status, '
                            . 'redirect_chain, robots_noindex, x_robots_noindex, backlink_found, best_link_type, anchor_text, page_authority, domain_authority, '
                            . 'provider_status, error_message, fetched_at, created_at) '
                            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                            [
                                $scanId,
                                $target['id'],
                                $analysis['source_url'],
                                $analysis['source_domain'],
                                $analysis['final_url'],
                                $analysis['final_domain'],
                                $analysis['http_status'],
                                $analysis['fetch_status'],
                                json_encode($analysis['redirect_chain']),
                                $analysis['robots_noindex'] ? 1 : 0,
                                $analysis['x_robots_noindex'] ? 1 : 0,
                                $analysis['backlink_found'] ? 1 : 0,
                                $analysis['best_link_type'],
                                $analysis['anchor_text'],
                                $metric['pa'],
                                $metric['da'],
                                $providerStatus,
                                $errorMessage,
                                gmdate('c'),
                                gmdate('c'),
                            ]
                        );

                        $resultId = $this->db->lastInsertId();
                        foreach (($analysis['links'] ?? []) as $link) {
                            $this->db->execute(
                                'INSERT INTO scan_links(result_id, href, resolved_url, rel, link_type, anchor_text, is_target, created_at) '
                                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                                [
                                    $resultId,
                                    $link['href'] ?? null,
                                    $link['resolved_url'] ?? null,
                                    $link['rel'] ?? null,
                                    $link['link_type'] ?? 'none',
                                    $link['anchor_text'] ?? null,
                                    !empty($link['is_target']) ? 1 : 0,
                                    gmdate('c'),
                                ]
                            );
                        }

                        $this->db->execute('UPDATE scan_targets SET status = ?, updated_at = ? WHERE id = ?', ['completed', gmdate('c'), $target['id']]);
                        $processed++;
                        $this->db->execute('UPDATE scans SET processed_targets = ?, updated_at = ? WHERE id = ?', [$processed, gmdate('c'), $scanId]);
                    }
                    $this->db->commit();
                } catch (\Throwable $e) {
                    $this->db->rollBack();
                    throw $e;
                }
            }

            $this->db->execute(
                'UPDATE scans SET status = ?, finished_at = ?, updated_at = ? WHERE id = ?',
                [ScanStatus::COMPLETED, gmdate('c'), gmdate('c'), $scanId]
            );

            $finalScan = $this->findScan($scanId);
            if ($finalScan !== null) {
                $this->notifications->notifyScanCompleted($finalScan);
            }
            $this->telemetry->track('scan.completed', ['scan_id' => $scanId]);
        } catch (Throwable $e) {
            $this->db->execute(
                'UPDATE scans SET status = ?, error_summary = ?, finished_at = ?, updated_at = ? WHERE id = ?',
                [ScanStatus::FAILED, $e->getMessage(), gmdate('c'), gmdate('c'), $scanId]
            );
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function results(int $scanId, array $filters = []): array
    {
        $sql = 'SELECT * FROM scan_results WHERE scan_id = ?';
        $params = [$scanId];

        if (!empty($filters['status'])) {
            $sql .= ' AND fetch_status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['link_type'])) {
            $sql .= ' AND best_link_type = ?';
            $params[] = $filters['link_type'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (source_url LIKE ? OR anchor_text LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sort = (string) ($filters['sort'] ?? 'id_desc');
        $orderBy = match ($sort) {
            'da_desc' => 'domain_authority DESC',
            'da_asc' => 'domain_authority ASC',
            'status_asc' => 'fetch_status ASC',
            default => 'id DESC',
        };

        $sql .= ' ORDER BY ' . $orderBy;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function trendAgainstPrevious(int $scanId): array
    {
        $scan = $this->findScan($scanId);
        if ($scan === null) {
            return ['has_previous' => false];
        }

        $previous = $this->db->fetchOne(
            'SELECT * FROM scans WHERE project_id = ? AND id < ? AND status = ? ORDER BY id DESC LIMIT 1',
            [$scan['project_id'], $scanId, ScanStatus::COMPLETED]
        );
        if ($previous === null) {
            return ['has_previous' => false];
        }

        $currentAgg = $this->db->fetchOne(
            'SELECT COUNT(*) AS total, SUM(backlink_found) AS backlinks, AVG(domain_authority) AS avg_da FROM scan_results WHERE scan_id = ?',
            [$scanId]
        );
        $prevAgg = $this->db->fetchOne(
            'SELECT COUNT(*) AS total, SUM(backlink_found) AS backlinks, AVG(domain_authority) AS avg_da FROM scan_results WHERE scan_id = ?',
            [$previous['id']]
        );

        return [
            'has_previous' => true,
            'previous_scan_id' => (int) $previous['id'],
            'delta_backlinks' => ((int) ($currentAgg['backlinks'] ?? 0)) - ((int) ($prevAgg['backlinks'] ?? 0)),
            'delta_avg_da' => round(((float) ($currentAgg['avg_da'] ?? 0.0)) - ((float) ($prevAgg['avg_da'] ?? 0.0)), 2),
            'current_total' => (int) ($currentAgg['total'] ?? 0),
            'previous_total' => (int) ($prevAgg['total'] ?? 0),
        ];
    }
}
