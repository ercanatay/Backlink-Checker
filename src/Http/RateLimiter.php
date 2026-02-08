<?php

declare(strict_types=1);

namespace BacklinkChecker\Http;

use BacklinkChecker\Database\Database;

final class RateLimiter
{
    public function __construct(private readonly Database $db)
    {
    }

    public function hit(string $key, int $limit, int $windowSeconds): bool
    {
        $now = time();
        $windowStart = $now - ($now % $windowSeconds);
        $windowIso = gmdate('c', $windowStart);

        $row = $this->db->fetchOne('SELECT id, hit_count, window_started_at FROM rate_limits WHERE key = ?', [$key]);
        if ($row === null) {
            $this->db->execute('INSERT INTO rate_limits(key, window_started_at, hit_count) VALUES (?, ?, 1)', [$key, $windowIso]);
            return true;
        }

        if ((string) $row['window_started_at'] !== $windowIso) {
            $this->db->execute('UPDATE rate_limits SET window_started_at = ?, hit_count = 1 WHERE id = ?', [$windowIso, $row['id']]);
            return true;
        }

        $hits = (int) $row['hit_count'];
        if ($hits >= $limit) {
            return false;
        }

        $this->db->execute('UPDATE rate_limits SET hit_count = hit_count + 1 WHERE id = ?', [$row['id']]);

        return true;
    }
}
