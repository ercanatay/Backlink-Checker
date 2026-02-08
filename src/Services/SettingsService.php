<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;

final class SettingsService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $key, array $default = []): array
    {
        $row = $this->db->fetchOne('SELECT value_json FROM settings WHERE key = ?', [$key]);
        if ($row === null) {
            return $default;
        }

        $decoded = json_decode((string) $row['value_json'], true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * @param array<string, mixed> $value
     */
    public function set(string $key, array $value): void
    {
        $this->db->execute(
            'INSERT INTO settings(key, value_json, updated_at) VALUES (?, ?, ?) '
            . 'ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = excluded.updated_at',
            [$key, json_encode($value), gmdate('c')]
        );
    }
}
