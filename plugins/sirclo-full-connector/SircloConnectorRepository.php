<?php

declare(strict_types=1);

use App\Config\Database;

final class SircloConnectorRepository
{
    public const PLUGIN_SLUG = 'sirclo-full-connector';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function ensureSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS sirclo_sync_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                entity_type VARCHAR(30) NOT NULL,
                direction VARCHAR(20) NOT NULL DEFAULT "outbound",
                event_name VARCHAR(60) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                reference_id VARCHAR(120) DEFAULT NULL,
                payload_preview MEDIUMTEXT NULL,
                response_preview MEDIUMTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sirclo_branch_created (branch_id, created_at),
                INDEX idx_sirclo_status (status),
                INDEX idx_sirclo_entity (entity_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function getBranchSetting(int $branchId, string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ?
             LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? $default : (string) $value;
    }

    public function getGlobalSetting(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare(
            'SELECT setting_val FROM app_settings
             WHERE setting_key = ?
             LIMIT 1'
        );
        $stmt->execute([$this->globalSettingKey($key)]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? $default : (string) $value;
    }

    public function getRecentLogs(?int $branchId = null, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        if ($branchId !== null && $branchId > 0) {
            $stmt = $this->db->prepare(
                'SELECT l.*, b.name AS branch_name
                 FROM sirclo_sync_logs l
                 LEFT JOIN branches b ON b.id = l.branch_id
                 WHERE l.branch_id = ?
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $branchId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            'SELECT l.*, b.name AS branch_name
             FROM sirclo_sync_logs l
             LEFT JOIN branches b ON b.id = l.branch_id
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getSummary(?int $branchId = null): array
    {
        if ($branchId !== null && $branchId > 0) {
            $stmt = $this->db->prepare(
                'SELECT
                    COUNT(*) AS total_logs,
                    SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) AS success_logs,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_logs,
                    SUM(CASE WHEN status IN ("failed", "config_missing") THEN 1 ELSE 0 END) AS failed_logs,
                    MAX(created_at) AS last_activity
                 FROM sirclo_sync_logs
                 WHERE branch_id = ?'
            );
            $stmt->execute([$branchId]);
            return $stmt->fetch() ?: [];
        }

        return $this->db->query(
            'SELECT
                COUNT(*) AS total_logs,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) AS success_logs,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_logs,
                SUM(CASE WHEN status IN ("failed", "config_missing") THEN 1 ELSE 0 END) AS failed_logs,
                MAX(created_at) AS last_activity
             FROM sirclo_sync_logs'
        )->fetch() ?: [];
    }

    public function getBranchStatuses(): array
    {
        return $this->db->query(
            'SELECT
                b.id,
                b.name,
                COALESCE(ps_active.setting_val, "0") AS is_active,
                COALESCE(ps_store.setting_val, "") AS store_id,
                COUNT(l.id) AS total_logs,
                MAX(l.created_at) AS last_activity,
                SUM(CASE WHEN l.status = "success" THEN 1 ELSE 0 END) AS success_logs,
                SUM(CASE WHEN l.status IN ("failed", "config_missing") THEN 1 ELSE 0 END) AS failed_logs
             FROM branches b
             LEFT JOIN plugin_branch_settings ps_active
               ON ps_active.branch_id = b.id
              AND ps_active.plugin_slug = "' . self::PLUGIN_SLUG . '"
              AND ps_active.setting_key = "is_active"
             LEFT JOIN plugin_branch_settings ps_store
               ON ps_store.branch_id = b.id
              AND ps_store.plugin_slug = "' . self::PLUGIN_SLUG . '"
              AND ps_store.setting_key = "store_id"
             LEFT JOIN sirclo_sync_logs l
               ON l.branch_id = b.id
             GROUP BY b.id, b.name, ps_active.setting_val, ps_store.setting_val
             ORDER BY b.name ASC'
        )->fetchAll();
    }

    public function logSync(
        int $branchId,
        string $entityType,
        string $eventName,
        string $status,
        ?string $referenceId,
        array $payload = [],
        array $response = [],
        string $direction = 'outbound'
    ): void {
        $this->db->prepare(
            'INSERT INTO sirclo_sync_logs
                (branch_id, entity_type, direction, event_name, status, reference_id, payload_preview, response_preview)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $branchId,
            $entityType,
            $direction,
            $eventName,
            $status,
            $referenceId,
            $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $response === [] ? null : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function globalSettingKey(string $key): string
    {
        return 'plugin_' . str_replace('-', '_', self::PLUGIN_SLUG) . '_' . $key;
    }
}
