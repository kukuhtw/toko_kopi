<?php

declare(strict_types=1);

use App\Config\Database;

final class GoSendDeliveryRepository
{
    public const PLUGIN_SLUG = 'gosend-delivery';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function ensureSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS gosend_delivery_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                order_id INT UNSIGNED NULL,
                event_name VARCHAR(60) NOT NULL,
                direction VARCHAR(20) NOT NULL DEFAULT "outbound",
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                sync_key VARCHAR(120) DEFAULT NULL,
                external_ref VARCHAR(120) DEFAULT NULL,
                tracking_url VARCHAR(255) DEFAULT NULL,
                attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                payload_preview MEDIUMTEXT NULL,
                response_preview MEDIUMTEXT NULL,
                http_status INT NULL,
                last_error TEXT NULL,
                next_retry_at DATETIME NULL,
                processed_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_gosend_logs_branch_created (branch_id, created_at),
                INDEX idx_gosend_logs_status (status),
                INDEX idx_gosend_logs_order (order_id),
                INDEX idx_gosend_logs_sync_key (sync_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS gosend_delivery_orders (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                order_id INT UNSIGNED NOT NULL,
                order_number VARCHAR(80) NOT NULL,
                external_ref VARCHAR(120) DEFAULT NULL,
                tracking_url VARCHAR(255) DEFAULT NULL,
                service_type VARCHAR(40) DEFAULT NULL,
                delivery_status VARCHAR(40) NOT NULL DEFAULT "queued",
                latest_note TEXT NULL,
                last_log_id INT UNSIGNED NULL,
                last_synced_at DATETIME NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_gosend_order (order_id),
                INDEX idx_gosend_delivery_branch_status (branch_id, delivery_status),
                INDEX idx_gosend_delivery_external_ref (external_ref)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS gosend_webhook_audits (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                order_id INT UNSIGNED NULL,
                order_number VARCHAR(80) DEFAULT NULL,
                external_ref VARCHAR(120) DEFAULT NULL,
                remote_status VARCHAR(60) DEFAULT NULL,
                old_order_status VARCHAR(60) DEFAULT NULL,
                new_order_status VARCHAR(60) DEFAULT NULL,
                payload_preview MEDIUMTEXT NULL,
                note TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_gosend_webhook_branch_created (branch_id, created_at)
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
        string $status,
        array $payload = [],
        ?string $syncKey = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO gosend_delivery_logs
             (branch_id, order_id, event_name, direction, status, sync_key, payload_preview)
             VALUES (?, ?, ?, "outbound", ?, ?, ?)'
        );
        $stmt->execute([
            $branchId,
            $orderId,
            $eventName,
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
        ?string $externalRef = null,
        ?string $trackingUrl = null,
        ?int $httpStatus = null,
        ?string $lastError = null,
        ?string $nextRetryAt = null
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE gosend_delivery_logs
             SET status = ?,
                 response_preview = ?,
                 external_ref = ?,
                 tracking_url = ?,
                 http_status = ?,
                 last_error = ?,
                 next_retry_at = ?,
                 attempt_count = attempt_count + 1,
                 processed_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $status,
            $response !== [] ? json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $externalRef,
            $trackingUrl,
            $httpStatus,
            $lastError,
            $nextRetryAt,
            $logId,
        ]);
    }

    public function upsertDeliveryOrderStatus(
        int $branchId,
        int $orderId,
        string $orderNumber,
        array $data
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO gosend_delivery_orders
             (branch_id, order_id, order_number, external_ref, tracking_url, service_type, delivery_status, latest_note, last_log_id, last_synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               external_ref = VALUES(external_ref),
               tracking_url = VALUES(tracking_url),
               service_type = VALUES(service_type),
               delivery_status = VALUES(delivery_status),
               latest_note = VALUES(latest_note),
               last_log_id = VALUES(last_log_id),
               last_synced_at = VALUES(last_synced_at)'
        );
        $stmt->execute([
            $branchId,
            $orderId,
            $orderNumber,
            $data['external_ref'] ?? null,
            $data['tracking_url'] ?? null,
            $data['service_type'] ?? null,
            $data['delivery_status'] ?? 'queued',
            $data['latest_note'] ?? null,
            $data['last_log_id'] ?? null,
            $data['last_synced_at'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function getPendingLogs(?int $branchId = null, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        if ($branchId !== null && $branchId > 0) {
            $stmt = $this->db->prepare(
                'SELECT * FROM gosend_delivery_logs
                 WHERE branch_id = ?
                   AND status IN ("pending","retry_scheduled")
                   AND (next_retry_at IS NULL OR next_retry_at <= NOW())
                 ORDER BY id ASC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $branchId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM gosend_delivery_logs
             WHERE status IN ("pending","retry_scheduled")
               AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             ORDER BY id ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getRecentLogs(?int $branchId = null, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        if ($branchId !== null && $branchId > 0) {
            $stmt = $this->db->prepare(
                'SELECT l.*, b.name AS branch_name, o.order_number
                 FROM gosend_delivery_logs l
                 LEFT JOIN branches b ON b.id = l.branch_id
                 LEFT JOIN orders o ON o.id = l.order_id
                 WHERE l.branch_id = ?
                 ORDER BY l.id DESC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $branchId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            'SELECT l.*, b.name AS branch_name, o.order_number
             FROM gosend_delivery_logs l
             LEFT JOIN branches b ON b.id = l.branch_id
             LEFT JOIN orders o ON o.id = l.order_id
             ORDER BY l.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getBranchOrderStatuses(int $branchId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, o.order_status, o.payment_status, o.total_amount
             FROM gosend_delivery_orders d
             LEFT JOIN orders o ON o.id = d.order_id
             WHERE d.branch_id = ?
             ORDER BY d.updated_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $branchId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findDeliveryOrder(int $branchId, string $orderNumber = '', string $externalRef = ''): array|false
    {
        $orderNumber = trim($orderNumber);
        $externalRef = trim($externalRef);
        $stmt = $this->db->prepare(
            'SELECT * FROM gosend_delivery_orders
             WHERE branch_id = ?
               AND (
                    (? <> "" AND order_number = ?)
                    OR
                    (? <> "" AND external_ref = ?)
               )
             LIMIT 1'
        );
        $stmt->execute([$branchId, $orderNumber, $orderNumber, $externalRef, $externalRef]);
        return $stmt->fetch();
    }

    public function addWebhookAudit(
        int $branchId,
        ?int $orderId,
        ?string $orderNumber,
        ?string $externalRef,
        ?string $remoteStatus,
        ?string $oldOrderStatus,
        ?string $newOrderStatus,
        array $payload,
        string $note
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO gosend_webhook_audits
             (branch_id, order_id, order_number, external_ref, remote_status, old_order_status, new_order_status, payload_preview, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $branchId,
            $orderId,
            $orderNumber,
            $externalRef,
            $remoteStatus,
            $oldOrderStatus,
            $newOrderStatus,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $note,
        ]);
    }

    public function getWebhookAudits(int $branchId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM gosend_webhook_audits
             WHERE branch_id = ?
             ORDER BY id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $branchId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getSummary(?int $branchId = null): array
    {
        $params = [];
        $where = '';
        if ($branchId !== null && $branchId > 0) {
            $where = 'WHERE branch_id = ?';
            $params[] = $branchId;
        }

        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*) AS total_logs,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) AS success_logs,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_logs,
                SUM(CASE WHEN status = "retry_scheduled" THEN 1 ELSE 0 END) AS retry_logs,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed_logs,
                MAX(created_at) AS last_activity
             FROM gosend_delivery_logs ' . $where
        );
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    }

    public function getBranchStatuses(): array
    {
        return $this->db->query(
            'SELECT
                b.id,
                b.name,
                COALESCE(ps_active.setting_val, "0") AS is_active,
                COALESCE(ps_service.setting_val, "instant") AS service_type,
                COUNT(d.id) AS total_delivery_orders,
                SUM(CASE WHEN d.delivery_status IN ("queued","searching_driver") THEN 1 ELSE 0 END) AS open_delivery_orders,
                MAX(d.updated_at) AS last_delivery_activity
             FROM branches b
             LEFT JOIN plugin_branch_settings ps_active
               ON ps_active.branch_id = b.id
              AND ps_active.plugin_slug = "' . self::PLUGIN_SLUG . '"
              AND ps_active.setting_key = "is_active"
             LEFT JOIN plugin_branch_settings ps_service
               ON ps_service.branch_id = b.id
              AND ps_service.plugin_slug = "' . self::PLUGIN_SLUG . '"
              AND ps_service.setting_key = "service_type"
             LEFT JOIN gosend_delivery_orders d
               ON d.branch_id = b.id
             GROUP BY b.id, b.name, ps_active.setting_val, ps_service.setting_val
             ORDER BY b.name ASC'
        )->fetchAll();
    }

    public function getOrderById(int $orderId): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        return $stmt->fetch();
    }

    public function getDeliveryOrderByOrderId(int $orderId): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, o.branch_id, o.order_status, o.payment_status
             FROM gosend_delivery_orders d
             LEFT JOIN orders o ON o.id = d.order_id
             WHERE d.order_id = ?
             LIMIT 1'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetch();
    }

    private function globalKey(string $key): string
    {
        return self::PLUGIN_SLUG . '_' . $key;
    }
}
