<?php

declare(strict_types=1);

namespace App\Models;

class BranchModel extends BaseModel
{
    protected string $table = 'branches';

    public function findBySlug(string $slug): array|false
    {
        return $this->query('SELECT * FROM branches WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug])->fetch();
    }

    public function getActive(): array
    {
        return $this->findAll('is_active = 1', [], 'name ASC');
    }

    public function getSetting(int $branchId, string $key, ?string $default = null): ?string
    {
        $row = $this->query(
            'SELECT setting_val FROM branch_settings WHERE branch_id = ? AND setting_key = ? LIMIT 1',
            [$branchId, $key]
        )->fetch();
        return $row ? $row['setting_val'] : $default;
    }

    public function setSetting(int $branchId, string $key, string $value): void
    {
        $this->query(
            'INSERT INTO branch_settings (branch_id, setting_key, setting_val)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_val = ?',
            [$branchId, $key, $value, $value]
        );
    }

    public function getAllSettings(int $branchId): array
    {
        $rows = $this->query('SELECT setting_key, setting_val FROM branch_settings WHERE branch_id = ?', [$branchId])->fetchAll();
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
        return (float)($this->getSetting($branchId, 'ppn_rate') ?? '11');
    }

    public function getTimezone(int $branchId): string
    {
        return $this->getSetting($branchId, 'timezone') ?? 'Asia/Jakarta';
    }
}
