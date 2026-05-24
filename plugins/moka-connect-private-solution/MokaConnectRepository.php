<?php

declare(strict_types=1);

use App\Config\Database;

final class MokaConnectRepository
{
    public const PLUGIN_SLUG = 'moka-connect-private-solution';
    public const MAPPING_KEYS = [
        'map_out_order_key',
        'map_out_order_id_key',
        'map_out_receipt_key',
        'map_out_status_key',
        'map_out_customer_key',
        'map_out_payment_key',
        'map_out_totals_key',
        'map_out_line_items_key',
        'map_out_metadata_key',
        'map_out_fulfillment_key',
        'map_in_order_number_path',
        'map_in_external_ref_path',
        'map_in_order_status_path',
        'map_in_payment_status_path',
        'map_order_status_pairs',
        'map_payment_status_pairs',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function ensureSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS moka_sync_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                order_id INT UNSIGNED NULL,
                entity_type VARCHAR(30) NOT NULL,
                direction VARCHAR(20) NOT NULL DEFAULT "outbound",
                event_name VARCHAR(60) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                reference_id VARCHAR(120) DEFAULT NULL,
                sync_key VARCHAR(120) DEFAULT NULL,
                attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                http_status INT NULL,
                external_ref VARCHAR(120) DEFAULT NULL,
                payload_preview MEDIUMTEXT NULL,
                response_preview MEDIUMTEXT NULL,
                last_error TEXT NULL,
                next_retry_at DATETIME NULL,
                processed_at DATETIME NULL,
                synced_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_moka_branch_created (branch_id, created_at),
                INDEX idx_moka_status (status),
                INDEX idx_moka_entity (entity_type),
                INDEX idx_moka_order (order_id),
                INDEX idx_moka_sync_key (sync_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS moka_order_sync_status (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                order_id INT UNSIGNED NOT NULL,
                order_number VARCHAR(80) NOT NULL,
                last_status VARCHAR(20) NOT NULL DEFAULT "pending",
                last_event_name VARCHAR(60) DEFAULT NULL,
                last_attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                last_http_status INT NULL,
                external_ref VARCHAR(120) DEFAULT NULL,
                last_error TEXT NULL,
                last_log_id INT UNSIGNED NULL,
                last_queued_at DATETIME NULL,
                last_synced_at DATETIME NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_moka_order_sync (order_id),
                INDEX idx_moka_order_sync_branch (branch_id, last_status),
                INDEX idx_moka_order_sync_log (last_log_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS moka_webhook_audits (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NOT NULL,
                order_id INT UNSIGNED NULL,
                order_number VARCHAR(80) DEFAULT NULL,
                source_type VARCHAR(30) NOT NULL DEFAULT "webhook",
                remote_order_status VARCHAR(60) DEFAULT NULL,
                remote_payment_status VARCHAR(60) DEFAULT NULL,
                old_order_status VARCHAR(60) DEFAULT NULL,
                new_order_status VARCHAR(60) DEFAULT NULL,
                old_payment_status VARCHAR(60) DEFAULT NULL,
                new_payment_status VARCHAR(60) DEFAULT NULL,
                changed_fields VARCHAR(255) DEFAULT NULL,
                payload_preview MEDIUMTEXT NULL,
                note TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_moka_audit_branch_created (branch_id, created_at),
                INDEX idx_moka_audit_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->ensureColumn('moka_sync_logs', 'order_id', 'INT UNSIGNED NULL AFTER branch_id');
        $this->ensureColumn('moka_sync_logs', 'sync_key', 'VARCHAR(120) DEFAULT NULL AFTER reference_id');
        $this->ensureColumn('moka_sync_logs', 'attempt_count', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER sync_key');
        $this->ensureColumn('moka_sync_logs', 'http_status', 'INT NULL AFTER attempt_count');
        $this->ensureColumn('moka_sync_logs', 'external_ref', 'VARCHAR(120) DEFAULT NULL AFTER http_status');
        $this->ensureColumn('moka_sync_logs', 'last_error', 'TEXT NULL AFTER response_preview');
        $this->ensureColumn('moka_sync_logs', 'next_retry_at', 'DATETIME NULL AFTER last_error');
        $this->ensureColumn('moka_sync_logs', 'processed_at', 'DATETIME NULL AFTER next_retry_at');
        $this->ensureColumn('moka_sync_logs', 'synced_at', 'DATETIME NULL AFTER processed_at');
        $this->ensureIndex('moka_sync_logs', 'idx_moka_order', 'order_id');
        $this->ensureIndex('moka_sync_logs', 'idx_moka_sync_key', 'sync_key');

        $this->ensureColumn('moka_order_sync_status', 'order_number', 'VARCHAR(80) NOT NULL DEFAULT "" AFTER order_id');
        $this->ensureColumn('moka_order_sync_status', 'last_status', 'VARCHAR(20) NOT NULL DEFAULT "pending" AFTER order_number');
        $this->ensureColumn('moka_order_sync_status', 'last_event_name', 'VARCHAR(60) DEFAULT NULL AFTER last_status');
        $this->ensureColumn('moka_order_sync_status', 'last_attempt_count', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_event_name');
        $this->ensureColumn('moka_order_sync_status', 'last_http_status', 'INT NULL AFTER last_attempt_count');
        $this->ensureColumn('moka_order_sync_status', 'external_ref', 'VARCHAR(120) DEFAULT NULL AFTER last_http_status');
        $this->ensureColumn('moka_order_sync_status', 'last_error', 'TEXT NULL AFTER external_ref');
        $this->ensureColumn('moka_order_sync_status', 'last_log_id', 'INT UNSIGNED NULL AFTER last_error');
        $this->ensureColumn('moka_order_sync_status', 'last_queued_at', 'DATETIME NULL AFTER last_log_id');
        $this->ensureColumn('moka_order_sync_status', 'last_synced_at', 'DATETIME NULL AFTER last_queued_at');
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
                'SELECT l.*, b.name AS branch_name, o.order_number
                 FROM moka_sync_logs l
                 LEFT JOIN branches b ON b.id = l.branch_id
                 LEFT JOIN orders o ON o.id = l.order_id
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
            'SELECT l.*, b.name AS branch_name, o.order_number
             FROM moka_sync_logs l
             LEFT JOIN branches b ON b.id = l.branch_id
             LEFT JOIN orders o ON o.id = l.order_id
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
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
                SUM(CASE WHEN status IN ("failed", "config_missing") THEN 1 ELSE 0 END) AS failed_logs,
                MAX(created_at) AS last_activity
             FROM moka_sync_logs
             ' . $where
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
                COALESCE(ps_outlet.setting_val, "") AS outlet_id,
                COALESCE(ps_merchant.setting_val, "") AS merchant_id,
                COUNT(l.id) AS total_logs,
                MAX(l.created_at) AS last_activity,
                SUM(CASE WHEN l.status = "success" THEN 1 ELSE 0 END) AS success_logs,
                SUM(CASE WHEN l.status = "retry_scheduled" THEN 1 ELSE 0 END) AS retry_logs,
                SUM(CASE WHEN l.status IN ("failed", "config_missing") THEN 1 ELSE 0 END) AS failed_logs
             FROM branches b
             LEFT JOIN plugin_branch_settings ps_active
               ON ps_active.branch_id = b.id
              AND ps_active.plugin_slug = "' . self::PLUGIN_SLUG . '"
              AND ps_active.setting_key = "is_active"
             LEFT JOIN plugin_branch_settings ps_outlet
               ON ps_outlet.branch_id = b.id
              AND ps_outlet.plugin_slug = "' . self::PLUGIN_SLUG . '"
              AND ps_outlet.setting_key = "outlet_id"
             LEFT JOIN plugin_branch_settings ps_merchant
               ON ps_merchant.branch_id = b.id
              AND ps_merchant.plugin_slug = "' . self::PLUGIN_SLUG . '"
              AND ps_merchant.setting_key = "merchant_id"
             LEFT JOIN moka_sync_logs l
               ON l.branch_id = b.id
             GROUP BY b.id, b.name, ps_active.setting_val, ps_outlet.setting_val, ps_merchant.setting_val
             ORDER BY b.name ASC'
        )->fetchAll();
    }

    public function queueSync(
        int $branchId,
        string $entityType,
        string $eventName,
        string $status,
        ?string $referenceId,
        array $payload = [],
        array $response = [],
        string $direction = 'outbound',
        ?int $orderId = null,
        ?string $syncKey = null,
        int $attemptCount = 0,
        ?int $httpStatus = null,
        ?string $externalRef = null,
        ?string $lastError = null,
        ?string $nextRetryAt = null
    ): int {
        $this->db->prepare(
            'INSERT INTO moka_sync_logs
                (branch_id, order_id, entity_type, direction, event_name, status, reference_id, sync_key,
                 attempt_count, http_status, external_ref, payload_preview, response_preview, last_error, next_retry_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $branchId,
            $orderId,
            $entityType,
            $direction,
            $eventName,
            $status,
            $referenceId,
            $syncKey,
            $attemptCount,
            $httpStatus,
            $externalRef,
            $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $response === [] ? null : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $lastError,
            $nextRetryAt,
        ]);

        return (int)$this->db->lastInsertId();
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
        $this->queueSync($branchId, $entityType, $eventName, $status, $referenceId, $payload, $response, $direction);
    }

    public function updateLogResult(
        int $logId,
        string $status,
        array $response = [],
        ?int $httpStatus = null,
        ?string $externalRef = null,
        ?string $lastError = null,
        ?string $nextRetryAt = null,
        ?int $attemptCount = null
    ): void {
        $sql = 'UPDATE moka_sync_logs
                SET status = ?,
                    response_preview = ?,
                    http_status = ?,
                    external_ref = ?,
                    last_error = ?,
                    next_retry_at = ?,
                    processed_at = NOW()';
        $params = [
            $status,
            $response === [] ? null : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $httpStatus,
            $externalRef,
            $lastError,
            $nextRetryAt,
        ];

        if ($attemptCount !== null) {
            $sql .= ', attempt_count = ?';
            $params[] = $attemptCount;
        }

        if ($status === 'success') {
            $sql .= ', synced_at = NOW()';
        }

        $sql .= ' WHERE id = ?';
        $params[] = $logId;

        $this->db->prepare($sql)->execute($params);
    }

    public function findLogById(int $logId): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, o.order_number
             FROM moka_sync_logs l
             LEFT JOIN orders o ON o.id = l.order_id
             WHERE l.id = ?
             LIMIT 1'
        );
        $stmt->execute([$logId]);

        return $stmt->fetch() ?: false;
    }

    public function getLatestOrderLog(int $orderId): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM moka_sync_logs
             WHERE order_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$orderId]);

        return $stmt->fetch() ?: false;
    }

    public function getRetryableLogs(int $branchId, array $statuses, int $limit = 20): array
    {
        $statuses = array_values(array_filter(array_map(
            static fn($status): string => (string)$status,
            $statuses
        )));
        if ($statuses === []) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = 'SELECT l.*, o.order_number
                FROM moka_sync_logs l
                LEFT JOIN orders o ON o.id = l.order_id
                WHERE l.branch_id = ?
                  AND l.entity_type = "order"
                  AND l.status IN (' . $placeholders . ')
                  AND (l.next_retry_at IS NULL OR l.next_retry_at <= NOW())
                ORDER BY l.created_at ASC, l.id ASC
                LIMIT ?';

        $stmt = $this->db->prepare($sql);
        $position = 1;
        $stmt->bindValue($position++, $branchId, PDO::PARAM_INT);
        foreach ($statuses as $status) {
            $stmt->bindValue($position++, $status, PDO::PARAM_STR);
        }
        $stmt->bindValue($position, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function upsertOrderSyncStatus(int $branchId, int $orderId, string $orderNumber, array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO moka_order_sync_status
                (branch_id, order_id, order_number, last_status, last_event_name, last_attempt_count,
                 last_http_status, external_ref, last_error, last_log_id, last_queued_at, last_synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                branch_id = VALUES(branch_id),
                order_number = VALUES(order_number),
                last_status = VALUES(last_status),
                last_event_name = VALUES(last_event_name),
                last_attempt_count = VALUES(last_attempt_count),
                last_http_status = VALUES(last_http_status),
                external_ref = VALUES(external_ref),
                last_error = VALUES(last_error),
                last_log_id = VALUES(last_log_id),
                last_queued_at = VALUES(last_queued_at),
                last_synced_at = VALUES(last_synced_at)'
        );
        $stmt->execute([
            $branchId,
            $orderId,
            $orderNumber,
            (string)($data['last_status'] ?? 'pending'),
            $data['last_event_name'] ?? null,
            (int)($data['last_attempt_count'] ?? 0),
            isset($data['last_http_status']) ? (int)$data['last_http_status'] : null,
            $data['external_ref'] ?? null,
            $data['last_error'] ?? null,
            isset($data['last_log_id']) ? (int)$data['last_log_id'] : null,
            $data['last_queued_at'] ?? null,
            $data['last_synced_at'] ?? null,
        ]);
    }

    public function getRecentOrderStatuses(int $branchId, int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            'SELECT
                s.*,
                o.total_amount,
                o.order_status,
                o.payment_status,
                o.created_at
             FROM moka_order_sync_status s
             JOIN orders o ON o.id = s.order_id
             WHERE s.branch_id = ?
             ORDER BY COALESCE(s.last_queued_at, o.created_at) DESC, s.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $branchId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getOrderSyncStatus(int $orderId): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM moka_order_sync_status WHERE order_id = ? LIMIT 1'
        );
        $stmt->execute([$orderId]);

        return $stmt->fetch() ?: false;
    }

    public function getOrderSyncStatusByExternalRef(int $branchId, string $externalRef): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM moka_order_sync_status
             WHERE branch_id = ? AND external_ref = ?
             LIMIT 1'
        );
        $stmt->execute([$branchId, $externalRef]);

        return $stmt->fetch() ?: false;
    }

    public function getActiveBranchIds(): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT branch_id
             FROM plugin_branch_settings
             WHERE plugin_slug = ?
               AND setting_key = "is_active"
               AND setting_val = "1"
             ORDER BY branch_id ASC'
        );
        $stmt->execute([self::PLUGIN_SLUG]);

        return array_map(static fn(array $row): int => (int)($row['branch_id'] ?? 0), $stmt->fetchAll());
    }

    public function getMappingConfig(int $branchId): array
    {
        if ($branchId <= 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count(self::MAPPING_KEYS), '?'));
        $stmt = $this->db->prepare(
            'SELECT setting_key, setting_val
             FROM plugin_branch_settings
             WHERE plugin_slug = ?
               AND branch_id = ?
               AND setting_key IN (' . $placeholders . ')'
        );
        $stmt->execute(array_merge([self::PLUGIN_SLUG, $branchId], self::MAPPING_KEYS));
        $rows = $stmt->fetchAll();

        return array_column($rows, 'setting_val', 'setting_key');
    }

    public function saveBranchSettings(int $branchId, array $settings): void
    {
        if ($branchId <= 0 || $settings === []) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO plugin_branch_settings (plugin_slug, branch_id, setting_key, setting_val)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
        );

        foreach ($settings as $key => $value) {
            $normalizedKey = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$key));
            if ($normalizedKey === '') {
                continue;
            }
            $stmt->execute([self::PLUGIN_SLUG, $branchId, $normalizedKey, (string)$value]);
        }
    }

    public function addWebhookAudit(
        int $branchId,
        ?int $orderId,
        ?string $orderNumber,
        string $sourceType,
        ?string $remoteOrderStatus,
        ?string $remotePaymentStatus,
        ?string $oldOrderStatus,
        ?string $newOrderStatus,
        ?string $oldPaymentStatus,
        ?string $newPaymentStatus,
        array $payloadPreview = [],
        array $changedFields = [],
        ?string $note = null
    ): void {
        $this->db->prepare(
            'INSERT INTO moka_webhook_audits
                (branch_id, order_id, order_number, source_type, remote_order_status, remote_payment_status,
                 old_order_status, new_order_status, old_payment_status, new_payment_status,
                 changed_fields, payload_preview, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $branchId,
            $orderId,
            $orderNumber,
            $sourceType,
            $remoteOrderStatus,
            $remotePaymentStatus,
            $oldOrderStatus,
            $newOrderStatus,
            $oldPaymentStatus,
            $newPaymentStatus,
            $changedFields === [] ? null : implode(', ', $changedFields),
            $payloadPreview === [] ? null : json_encode($payloadPreview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $note,
        ]);
    }

    public function getRecentWebhookAudits(int $branchId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            'SELECT *
             FROM moka_webhook_audits
             WHERE branch_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $branchId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function globalSettingKey(string $key): string
    {
        return 'plugin_' . str_replace('-', '_', self::PLUGIN_SLUG) . '_' . $key;
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $stmt = $this->db->prepare(
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

        $this->db->exec(sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s',
            $table,
            $column,
            $definition
        ));
    }

    private function ensureIndex(string $table, string $indexName, string $columnList): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?
             LIMIT 1'
        );
        $stmt->execute([$table, $indexName]);
        if ($stmt->fetchColumn()) {
            return;
        }

        $this->db->exec(sprintf(
            'ALTER TABLE %s ADD INDEX %s (%s)',
            $table,
            $indexName,
            $columnList
        ));
    }
}
