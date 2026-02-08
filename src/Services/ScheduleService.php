<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;
use BacklinkChecker\Exceptions\ValidationException;

final class ScheduleService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<int, string> $targets
     */
    public function create(
        int $projectId,
        int $createdBy,
        string $name,
        string $rootDomain,
        array $targets,
        string $rrule,
        string $timezone
    ): int {
        $rrule = strtoupper(trim($rrule));
        if (!$this->isSupportedRrule($rrule)) {
            throw new ValidationException('Unsupported RRULE. Use hourly or weekly rule format');
        }

        $nextRun = $this->nextRunAt($rrule, $timezone, new \DateTimeImmutable('now', new \DateTimeZone($timezone)));

        $this->db->execute(
            'INSERT INTO schedules(project_id, name, root_domain, targets_json, rrule, timezone, next_run_at, created_by, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $projectId,
                trim($name),
                trim($rootDomain),
                json_encode(array_values($targets)),
                $rrule,
                $timezone,
                $nextRun->setTimezone(new \DateTimeZone('UTC'))->format('c'),
                $createdBy,
                gmdate('c'),
                gmdate('c'),
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dueSchedules(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM schedules WHERE is_active = 1 AND next_run_at <= ? ORDER BY next_run_at ASC',
            [gmdate('c')]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByProject(int $projectId): array
    {
        return $this->db->fetchAll('SELECT * FROM schedules WHERE project_id = ? ORDER BY created_at DESC', [$projectId]);
    }

    public function markRun(int $scheduleId, int $scanId, string $status, ?string $errorMessage = null): void
    {
        $schedule = $this->db->fetchOne('SELECT * FROM schedules WHERE id = ?', [$scheduleId]);
        if ($schedule === null) {
            return;
        }

        $timezone = (string) ($schedule['timezone'] ?? 'UTC');
        $next = $this->nextRunAt((string) $schedule['rrule'], $timezone, new \DateTimeImmutable('now', new \DateTimeZone($timezone)));

        $this->db->execute(
            'INSERT INTO schedule_runs(schedule_id, scan_id, status, error_message, created_at, completed_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$scheduleId, $scanId, $status, $errorMessage, gmdate('c'), gmdate('c')]
        );

        $this->db->execute(
            'UPDATE schedules SET last_run_at = ?, next_run_at = ?, updated_at = ? WHERE id = ?',
            [gmdate('c'), $next->setTimezone(new \DateTimeZone('UTC'))->format('c'), gmdate('c'), $scheduleId]
        );
    }

    private function isSupportedRrule(string $rrule): bool
    {
        if (str_contains($rrule, 'FREQ=HOURLY')) {
            return true;
        }

        return str_contains($rrule, 'FREQ=WEEKLY') && str_contains($rrule, 'BYDAY=');
    }

    private function nextRunAt(string $rrule, string $timezone, \DateTimeImmutable $from): \DateTimeImmutable
    {
        $parts = [];
        foreach (explode(';', $rrule) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            if ($k !== '') {
                $parts[strtoupper($k)] = strtoupper($v);
            }
        }

        if (($parts['FREQ'] ?? '') === 'HOURLY') {
            $interval = max(1, (int) ($parts['INTERVAL'] ?? 1));
            return $from->modify('+' . $interval . ' hour');
        }

        $days = explode(',', (string) ($parts['BYDAY'] ?? 'MO'));
        $byHour = (int) ($parts['BYHOUR'] ?? 9);
        $byMinute = (int) ($parts['BYMINUTE'] ?? 0);

        $targetDow = [
            'SU' => 0,
            'MO' => 1,
            'TU' => 2,
            'WE' => 3,
            'TH' => 4,
            'FR' => 5,
            'SA' => 6,
        ];

        $candidate = $from->setTime($byHour, $byMinute);
        for ($i = 0; $i < 14; $i++) {
            $dow = (int) $candidate->format('w');
            $code = array_search($dow, $targetDow, true);
            if ($code !== false && in_array($code, $days, true) && $candidate > $from) {
                return $candidate;
            }
            $candidate = $candidate->modify('+1 day')->setTime($byHour, $byMinute);
        }

        return $from->modify('+7 day')->setTime($byHour, $byMinute);
    }
}
