<?php

declare(strict_types=1);

namespace KopiBot\Core;

use PDO;
use Throwable;

class MigrationRunner
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Database::getConnection();
    }

    public function run(string $migrationPath): array
    {
        $this->ensureMigrationsTable();

        $files = glob(rtrim($migrationPath, '/') . '/*.sql') ?: [];
        sort($files);

        $results = [];

        foreach ($files as $file) {
            $migration = basename($file);

            if ($this->hasRun($migration)) {
                $results[] = ['migration' => $migration, 'status' => 'skipped'];
                continue;
            }

            try {
                $sql = file_get_contents($file);
                $this->db->exec($sql);
                $this->markRun($migration);
                $results[] = ['migration' => $migration, 'status' => 'success'];
            } catch (Throwable $e) {
                $results[] = ['migration' => $migration, 'status' => 'failed', 'error' => $e->getMessage()];
                break;
            }
        }

        return $results;
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS migrations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, executed_at DATETIME NULL, UNIQUE KEY uq_migration (migration))'
        );
    }

    private function hasRun(string $migration): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM migrations WHERE migration = :migration');
        $stmt->execute(['migration' => $migration]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function markRun(string $migration): void
    {
        $stmt = $this->db->prepare('INSERT INTO migrations (migration, executed_at) VALUES (:migration, NOW())');
        $stmt->execute(['migration' => $migration]);
    }
}
