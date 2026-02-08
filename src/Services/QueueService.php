<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Support\Uuid;

final class QueueService
{
    public function __construct(
        private readonly Database $db,
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(string $type, array $payload, ?string $availableAt = null, ?string $correlationId = null): int
    {
        $now = gmdate('c');
        $this->db->execute(
            'INSERT INTO jobs(type, payload_json, status, attempts, max_attempts, available_at, correlation_id, created_at, updated_at) '
            . 'VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?)',
            [
                $type,
                json_encode($payload),
                'queued',
                $this->config->int('QUEUE_MAX_ATTEMPTS', 3),
                $availableAt ?? $now,
                $correlationId ?? Uuid::v4(),
                $now,
                $now,
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function reserveNext(): ?array
    {
        $now = gmdate('c');

        $this->db->beginTransaction();
        try {
            $job = $this->db->fetchOne(
                'SELECT * FROM jobs WHERE status = ? AND available_at <= ? ORDER BY id ASC LIMIT 1',
                ['queued', $now]
            );

            if ($job === null) {
                $this->db->commit();
                return null;
            }

            $this->db->execute(
                'UPDATE jobs SET status = ?, locked_at = ?, updated_at = ? WHERE id = ? AND status = ?',
                ['running', $now, $now, $job['id'], 'queued']
            );

            $check = $this->db->fetchOne('SELECT status FROM jobs WHERE id = ?', [$job['id']]);
            if (($check['status'] ?? '') !== 'running') {
                $this->db->rollBack();
                return null;
            }

            $this->db->commit();
            return $this->db->fetchOne('SELECT * FROM jobs WHERE id = ?', [$job['id']]);
        } catch (\Throwable) {
            $this->db->rollBack();
            return null;
        }
    }

    public function complete(int $jobId): void
    {
        $this->db->execute(
            'UPDATE jobs SET status = ?, finished_at = ?, updated_at = ? WHERE id = ?',
            ['completed', gmdate('c'), gmdate('c'), $jobId]
        );
    }

    public function fail(int $jobId, string $message): void
    {
        $job = $this->db->fetchOne('SELECT attempts, max_attempts FROM jobs WHERE id = ?', [$jobId]);
        if ($job === null) {
            return;
        }

        $attempts = (int) $job['attempts'] + 1;
        $maxAttempts = (int) $job['max_attempts'];

        if ($attempts >= $maxAttempts) {
            $this->db->execute(
                'UPDATE jobs SET status = ?, attempts = ?, last_error = ?, finished_at = ?, updated_at = ? WHERE id = ?',
                ['dead', $attempts, $message, gmdate('c'), gmdate('c'), $jobId]
            );
            return;
        }

        $delay = 2 ** $attempts;
        $this->db->execute(
            'UPDATE jobs SET status = ?, attempts = ?, last_error = ?, available_at = ?, updated_at = ? WHERE id = ?',
            ['queued', $attempts, $message, gmdate('c', time() + $delay), gmdate('c'), $jobId]
        );
    }
}
