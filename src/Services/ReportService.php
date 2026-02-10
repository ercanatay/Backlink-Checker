<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Exceptions\ValidationException;

final class ReportService
{
    public function __construct(
        private readonly Database $db,
        private readonly Config $config
    ) {
    }

    public function createSchedule(int $projectId, int $userId, string $frequency, string $recipients): int
    {
        $frequency = strtolower(trim($frequency));
        if (!in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            throw new ValidationException('Frequency must be daily, weekly, or monthly');
        }

        $recipients = trim($recipients);
        if ($recipients === '') {
            throw new ValidationException('At least one recipient email is required');
        }

        $now = gmdate('c');
        $this->db->execute(
            'INSERT INTO report_schedules(project_id, frequency, recipients, is_active, created_by, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?, ?)',
            [$projectId, $frequency, $recipients, $userId, $now, $now]
        );

        return $this->db->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByProject(int $projectId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM report_schedules WHERE project_id = ? ORDER BY id DESC',
            [$projectId]
        );
    }

    public function toggleActive(int $scheduleId, bool $active): void
    {
        $this->db->execute(
            'UPDATE report_schedules SET is_active = ?, updated_at = ? WHERE id = ?',
            [$active ? 1 : 0, gmdate('c'), $scheduleId]
        );
    }

    /**
     * Generate report content for a project.
     *
     * @return array<string, mixed>
     */
    public function generateReport(int $projectId): array
    {
        $latestScan = $this->db->fetchOne(
            'SELECT * FROM scans WHERE project_id = ? AND status = ? ORDER BY id DESC LIMIT 1',
            [$projectId, 'completed']
        );

        if ($latestScan === null) {
            return ['has_data' => false];
        }

        $agg = $this->db->fetchOne(
            'SELECT COUNT(*) AS total, SUM(backlink_found) AS backlinks, AVG(domain_authority) AS avg_da FROM scan_results WHERE scan_id = ?',
            [$latestScan['id']]
        );

        $healthScore = $this->db->fetchOne('SELECT overall_score FROM health_scores WHERE scan_id = ?', [$latestScan['id']]);
        $velocity = $this->db->fetchOne('SELECT gained, lost, net FROM velocity_snapshots WHERE scan_id = ?', [$latestScan['id']]);

        return [
            'has_data' => true,
            'scan_id' => (int) $latestScan['id'],
            'scan_date' => (string) $latestScan['finished_at'],
            'total_results' => (int) ($agg['total'] ?? 0),
            'backlink_count' => (int) ($agg['backlinks'] ?? 0),
            'avg_da' => round((float) ($agg['avg_da'] ?? 0), 2),
            'health_score' => $healthScore !== null ? (float) $healthScore['overall_score'] : null,
            'velocity' => $velocity,
        ];
    }

    /**
     * Send pending reports that are due.
     */
    public function sendDueReports(): int
    {
        $schedules = $this->db->fetchAll('SELECT * FROM report_schedules WHERE is_active = 1');
        $sent = 0;

        $from = preg_replace('/[\r\n]/', '', $this->config->string('SMTP_FROM', 'noreply@example.com'));

        foreach ($schedules as $schedule) {
            if (!$this->isDue($schedule)) {
                continue;
            }

            $report = $this->generateReport((int) $schedule['project_id']);
            if (!($report['has_data'] ?? false)) {
                continue;
            }

            $recipients = array_filter(array_map('trim', explode(',', (string) $schedule['recipients'])));
            $subject = '[Backlink Checker] Report - Project #' . $schedule['project_id'];
            $body = $this->formatReportBody($report);

            foreach ($recipients as $email) {
                $email = preg_replace('/[\r\n]/', '', $email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL) && function_exists('mail')) {
                    @mail($email, $subject, $body, 'From: ' . $from);
                }
            }

            $this->db->execute(
                'UPDATE report_schedules SET last_sent_at = ?, updated_at = ? WHERE id = ?',
                [gmdate('c'), gmdate('c'), $schedule['id']]
            );
            $sent++;
        }

        return $sent;
    }

    /**
     * @param array<string, mixed> $schedule
     */
    private function isDue(array $schedule): bool
    {
        $lastSent = (string) ($schedule['last_sent_at'] ?? '');
        if ($lastSent === '') {
            return true;
        }

        $lastTime = strtotime($lastSent);
        $now = time();
        $frequency = (string) $schedule['frequency'];

        return match ($frequency) {
            'daily' => ($now - $lastTime) >= 86400,
            'weekly' => ($now - $lastTime) >= 604800,
            'monthly' => ($now - $lastTime) >= 2592000,
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $report
     */
    private function formatReportBody(array $report): string
    {
        $lines = [
            'Backlink Checker Pro - Report',
            '=============================',
            '',
            'Scan #' . ($report['scan_id'] ?? 'N/A'),
            'Date: ' . ($report['scan_date'] ?? 'N/A'),
            'Total Results: ' . ($report['total_results'] ?? 0),
            'Backlinks Found: ' . ($report['backlink_count'] ?? 0),
            'Average DA: ' . ($report['avg_da'] ?? 'N/A'),
        ];

        if (isset($report['health_score'])) {
            $lines[] = 'Health Score: ' . $report['health_score'] . '/100';
        }

        if (isset($report['velocity'])) {
            $v = $report['velocity'];
            $lines[] = 'Velocity: +' . ($v['gained'] ?? 0) . ' / -' . ($v['lost'] ?? 0) . ' (net: ' . ($v['net'] ?? 0) . ')';
        }

        return implode("\n", $lines) . "\n";
    }
}
