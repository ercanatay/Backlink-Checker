<?php

declare(strict_types=1);

namespace BacklinkChecker\Database;

use BacklinkChecker\Logging\JsonLogger;

final class Migrator
{
    public function __construct(
        private readonly Database $db,
        private readonly string $migrationsPath,
        private readonly JsonLogger $logger
    ) {
    }

    public function migrate(): void
    {
        $this->db->execute('CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
        $appliedRows = $this->db->fetchAll('SELECT version FROM schema_migrations');
        $applied = array_flip(array_map(static fn(array $row): string => (string) $row['version'], $appliedRows));

        $files = glob(rtrim($this->migrationsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $version = basename($file);
            if (isset($applied[$version])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }

            $this->db->transaction(function () use ($sql, $version): void {
                $this->db->pdo()->exec($sql);
                $this->db->execute(
                    'INSERT INTO schema_migrations(version, applied_at) VALUES (?, ?)',
                    [$version, gmdate('c')]
                );
            });

            $this->logger->info('migration_applied', ['version' => $version]);
        }
    }
}
