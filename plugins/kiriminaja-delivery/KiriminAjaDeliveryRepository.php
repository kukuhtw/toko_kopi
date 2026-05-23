<?php

declare(strict_types=1);

use App\Config\Database;

final class KiriminAjaDeliveryRepository
{
    public const PLUGIN_SLUG = 'kiriminaja-delivery';
    private static bool $schemaReady = false;

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $this->ensureColumn('orders', 'delivery_fee', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount_amount');
        $this->ensureColumn('orders', 'delivery_courier', 'VARCHAR(50) DEFAULT NULL AFTER delivery_fee');
        $this->ensureColumn('orders', 'delivery_service', 'VARCHAR(100) DEFAULT NULL AFTER delivery_courier');
        $this->ensureColumn('orders', 'delivery_provider', 'VARCHAR(50) DEFAULT NULL AFTER delivery_service');
        $this->ensureColumn('orders', 'delivery_reference', 'VARCHAR(100) DEFAULT NULL AFTER delivery_provider');

        self::$schemaReady = true;
    }

    public function getBranchSetting(int $branchId, string $key, string $default = ''): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ?
             LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? $default : (string)$value;
    }

    public function setBranchSetting(int $branchId, string $key, string $value): void
    {
        $stmt = Database::getInstance()->prepare(
            'INSERT INTO plugin_branch_settings (plugin_slug, branch_id, setting_key, setting_val)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key, $value]);
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?
             LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        if ($stmt->fetchColumn()) {
            return;
        }

        $db->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }
}
