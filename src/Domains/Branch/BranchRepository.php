<?php

declare(strict_types=1);

namespace KopiBot\Domains\Branch;

use KopiBot\Core\DatabaseConnection;
use PDO;
use PDOStatement;

final class BranchRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DatabaseConnection::getInstance();
        $this->ensureSchema();
    }

    public function findByCode(string $branchCode): ?array
    {
        $row = $this->query('SELECT * FROM branches WHERE branch_code = ? LIMIT 1', [$branchCode])->fetch();

        return $row ?: null;
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->query('SELECT * FROM branches WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug])->fetch();
    }

    public function getActive(): array
    {
        return $this->query('SELECT * FROM branches WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
    }

    public function getSetting(int $branchId, string $key, ?string $default = null): ?string
    {
        $row = $this->query(
            'SELECT setting_val FROM branch_settings WHERE branch_id = ? AND setting_key = ? LIMIT 1',
            [$branchId, $key]
        )->fetch();

        return $row ? (string) $row['setting_val'] : $default;
    }

    public function setSetting(int $branchId, string $key, string $value): void
    {
        $this->query(
            'INSERT INTO branch_settings (branch_id, setting_key, setting_val) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_val = ?',
            [$branchId, $key, $value, $value]
        );
    }

    public function getAllSettings(int $branchId): array
    {
        $rows = $this->query(
            'SELECT setting_key, setting_val FROM branch_settings WHERE branch_id = ?',
            [$branchId]
        )->fetchAll();

        return array_column($rows, 'setting_val', 'setting_key');
    }

    public function getCurrency(int $branchId): string
    {
        return $this->getSetting($branchId, 'currency') ?? 'IDR';
    }

    public function getLanguage(int $branchId): string
    {
        return $this->getSetting($branchId, 'language') ?? 'id';
    }

    public function getPpnRate(int $branchId): float
    {
        return (float) ($this->getSetting($branchId, 'ppn_rate') ?? '11');
    }

    public function getTimezone(int $branchId): string
    {
        return $this->getSetting($branchId, 'timezone') ?? 'Asia/Jakarta';
    }

    public function getBusinessType(int $branchId): string
    {
        return $this->getSetting($branchId, 'business_type') ?? 'toko';
    }

    private function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    private function ensureSchema(): void
    {
        $stmt = $this->query(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            ['branches', 'postal_code']
        );

        if (!$stmt->fetchColumn()) {
            $this->db->exec('ALTER TABLE branches ADD COLUMN postal_code VARCHAR(10) DEFAULT NULL AFTER city');
        }
    }
}
