<?php

declare(strict_types=1);

use KopiBot\Core\DatabaseConnection;

final class TiktokShopIntegrationRepository
{
    public const PLUGIN_SLUG = 'tiktokshop-integration';

    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();
    }

    public function ensureSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS tiktokshop_sync_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                order_id INT UNSIGNED NULL,
                event_name VARCHAR(80) NOT NULL,
                entity_type VARCHAR(40) NOT NULL DEFAULT "order",
                direction VARCHAR(20) NOT NULL DEFAULT "outbound",
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                external_ref VARCHAR(120) DEFAULT NULL,
                sync_key VARCHAR(120) DEFAULT NULL,
                payload_preview MEDIUMTEXT NULL,
                response_preview MEDIUMTEXT NULL,
                http_status INT NULL,
                last_error TEXT NULL,
                processed_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tiktokshop_logs_branch_created (branch_id, created_at),
                INDEX idx_tiktokshop_logs_status (status),
                INDEX idx_tiktokshop_logs_order (order_id),
                INDEX idx_tiktokshop_logs_sync_key (sync_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS tiktokshop_webhook_audits (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                topic VARCHAR(80) DEFAULT NULL,
                event_id VARCHAR(120) DEFAULT NULL,
                signature_status VARCHAR(20) NOT NULL DEFAULT "unchecked",
                payload_preview MEDIUMTEXT NULL,
                note TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tiktokshop_webhook_branch_created (branch_id, created_at),
                INDEX idx_tiktokshop_webhook_event (event_id)
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

    public function setBranchSetting(int $branchId, string $key, string $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO plugin_branch_settings (plugin_slug, branch_id, setting_key, setting_val)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key, $value]);
    }

    public function getGlobalSetting(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare(
            'SELECT setting_val FROM app_settings WHERE setting_key = ? LIMIT 1'
        );
        $stmt->execute([$this->globalKey($key)]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? $default : (string) $value;
    }

    public function setGlobalSetting(string $key, string $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO app_settings (setting_key, setting_val)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
        );
        $stmt->execute([$this->globalKey($key), $value]);
    }

    public function queueLog(
        int $branchId,
        ?int $orderId,
        string $eventName,
        string $entityType,
        string $status,
        array $payload = [],
        ?string $syncKey = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO tiktokshop_sync_logs
             (branch_id, order_id, event_name, entity_type, direction, status, sync_key, payload_preview)
             VALUES (?, ?, ?, ?, "outbound", ?, ?, ?)'
        );
        $stmt->execute([
            $branchId,
            $orderId,
            $eventName,
            $entityType,
            $status,
            $syncKey,
            $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markLogProcessed(
        int $logId,
        string $status,
        array $response = [],
        ?int $httpStatus = null,
        ?string $externalRef = null,
        ?string $lastError = null
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE tiktokshop_sync_logs
             SET status = ?,
                 response_preview = ?,
                 http_status = ?,
                 external_ref = ?,
                 last_error = ?,
                 processed_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $status,
            $response !== [] ? json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $httpStatus,
            $externalRef,
            $lastError,
            $logId,
        ]);
    }

    public function addWebhookAudit(
        int $branchId,
        ?string $topic,
        ?string $eventId,
        string $signatureStatus,
        array $payload = [],
        ?string $note = null
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO tiktokshop_webhook_audits
             (branch_id, topic, event_id, signature_status, payload_preview, note)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $branchId,
            $topic,
            $eventId,
            $signatureStatus,
            $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $note,
        ]);
    }

    public function getRecentLogs(?int $branchId = null, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        if ($branchId !== null && $branchId > 0) {
            $stmt = $this->db->prepare(
                'SELECT l.*, b.name AS branch_name, o.order_number
                 FROM tiktokshop_sync_logs l
                 LEFT JOIN branches b ON b.id = l.branch_id
                 LEFT JOIN orders o ON o.id = l.order_id
                 WHERE l.branch_id = ?
                 ORDER BY l.id DESC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $branchId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        }

        $stmt = $this->db->prepare(
            'SELECT l.*, b.name AS branch_name, o.order_number
             FROM tiktokshop_sync_logs l
             LEFT JOIN branches b ON b.id = l.branch_id
             LEFT JOIN orders o ON o.id = l.order_id
             ORDER BY l.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getBranchOverview(?int $branchId = null): array
    {
        if ($branchId !== null && $branchId > 0) {
            $stmt = $this->db->prepare(
                'SELECT
                    COUNT(*) AS total_logs,
                    SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) AS success_logs,
                    SUM(CASE WHEN status IN ("pending","queued") THEN 1 ELSE 0 END) AS pending_logs,
                    SUM(CASE WHEN status IN ("failed","error") THEN 1 ELSE 0 END) AS failed_logs
                 FROM tiktokshop_sync_logs
                 WHERE branch_id = ?'
            );
            $stmt->execute([$branchId]);
            return $stmt->fetch() ?: [];
        }

        return $this->db->query(
            'SELECT
                COUNT(*) AS total_logs,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) AS success_logs,
                SUM(CASE WHEN status IN ("pending","queued") THEN 1 ELSE 0 END) AS pending_logs,
                SUM(CASE WHEN status IN ("failed","error") THEN 1 ELSE 0 END) AS failed_logs
             FROM tiktokshop_sync_logs'
        )->fetch() ?: [];
    }

    private function globalKey(string $key): string
    {
        return self::PLUGIN_SLUG . '.' . $key;
    }
}
