<?php

declare(strict_types=1);

use App\Config\Database;

final class TikTokShopLiveRepository
{
    public const PLUGIN_SLUG = 'tiktok-shop-live';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function ensureSchema(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS tiktok_sync_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT UNSIGNED NOT NULL DEFAULT 0,
            entity_type VARCHAR(40) NOT NULL,
            direction VARCHAR(20) NOT NULL DEFAULT "inbound",
            event_name VARCHAR(100) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT "pending",
            reference_id VARCHAR(120) DEFAULT NULL,
            payload_preview MEDIUMTEXT NULL,
            response_preview MEDIUMTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tiktok_sync_branch_created (branch_id, created_at),
            INDEX idx_tiktok_sync_status (status),
            INDEX idx_tiktok_sync_entity (entity_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->db->exec('CREATE TABLE IF NOT EXISTS tiktok_catalog_maps (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT UNSIGNED NOT NULL,
            menu_item_id INT UNSIGNED NOT NULL,
            variant_id INT UNSIGNED NOT NULL DEFAULT 0,
            tiktok_product_id VARCHAR(120) NOT NULL,
            tiktok_sku_id VARCHAR(120) DEFAULT NULL,
            external_sku VARCHAR(120) DEFAULT NULL,
            stock_quantity DECIMAL(12,2) DEFAULT NULL,
            external_price DECIMAL(12,2) DEFAULT NULL,
            external_payload MEDIUMTEXT NULL,
            synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tiktok_catalog_external (branch_id, tiktok_product_id, tiktok_sku_id),
            UNIQUE KEY uq_tiktok_catalog_local (branch_id, menu_item_id, variant_id),
            KEY idx_tiktok_catalog_branch (branch_id),
            KEY idx_tiktok_catalog_item (menu_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->db->exec('CREATE TABLE IF NOT EXISTS tiktok_orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT UNSIGNED NOT NULL DEFAULT 0,
            tiktok_order_id VARCHAR(120) NOT NULL,
            customer_name VARCHAR(180) DEFAULT NULL,
            total_amount DECIMAL(12,2) DEFAULT 0,
            order_status VARCHAR(60) DEFAULT NULL,
            payload_json MEDIUMTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tiktok_order (branch_id, tiktok_order_id),
            KEY idx_tiktok_order_status (order_status),
            KEY idx_tiktok_order_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->db->exec('CREATE TABLE IF NOT EXISTS tiktok_live_metrics (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT UNSIGNED NOT NULL,
            live_id VARCHAR(120) NOT NULL,
            viewers INT DEFAULT 0,
            likes_count INT DEFAULT 0,
            comments_count INT DEFAULT 0,
            orders_count INT DEFAULT 0,
            revenue_amount DECIMAL(12,2) DEFAULT 0,
            payload_json MEDIUMTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tiktok_live(branch_id,live_id),
            INDEX idx_tiktok_live_created(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->db->exec('CREATE TABLE IF NOT EXISTS tiktok_ai_recommendations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT UNSIGNED NOT NULL,
            live_id VARCHAR(120) DEFAULT NULL,
            recommendation_type VARCHAR(80) NOT NULL DEFAULT "live_host_assistant",
            recommendation_text MEDIUMTEXT NOT NULL,
            source_payload MEDIUMTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_tiktok_ai_branch_created(branch_id, created_at),
            KEY idx_tiktok_ai_live(live_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public function getBranchSetting(int $branchId, string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare('SELECT setting_val FROM plugin_branch_settings WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1');
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : (string) $value;
    }

    public function saveOrder(int $branchId, array $payload): void
    {
        $orderId = (string)($payload['order_id'] ?? $payload['id'] ?? $payload['data']['order_id'] ?? '');
        if ($orderId === '') {
            $orderId = 'unknown-' . sha1(json_encode($payload));
        }

        $this->db->prepare('INSERT INTO tiktok_orders (branch_id, tiktok_order_id, customer_name, total_amount, order_status, payload_json)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE customer_name=VALUES(customer_name), total_amount=VALUES(total_amount), order_status=VALUES(order_status), payload_json=VALUES(payload_json)')
            ->execute([
                $branchId,
                $orderId,
                (string)($payload['customer_name'] ?? $payload['buyer_name'] ?? ''),
                (float)($payload['total_amount'] ?? $payload['payment']['total_amount'] ?? 0),
                (string)($payload['status'] ?? $payload['order_status'] ?? 'unknown'),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
    }

    public function saveLiveMetric(int $branchId, array $payload): void
    {
        $this->db->prepare('INSERT INTO tiktok_live_metrics (branch_id, live_id, viewers, likes_count, comments_count, orders_count, revenue_amount, payload_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $branchId,
                (string)($payload['live_id'] ?? $payload['room_id'] ?? 'unknown'),
                (int)($payload['viewers'] ?? 0),
                (int)($payload['likes_count'] ?? $payload['likes'] ?? 0),
                (int)($payload['comments_count'] ?? $payload['comments'] ?? 0),
                (int)($payload['orders_count'] ?? $payload['orders'] ?? 0),
                (float)($payload['revenue_amount'] ?? $payload['revenue'] ?? 0),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
    }

    public function saveRecommendation(int $branchId, ?string $liveId, string $text, array $source = [], string $type = 'live_host_assistant'): void
    {
        $this->db->prepare('INSERT INTO tiktok_ai_recommendations (branch_id, live_id, recommendation_type, recommendation_text, source_payload) VALUES (?, ?, ?, ?, ?)')
            ->execute([$branchId, $liveId, $type, $text, json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    public function logSync(int $branchId, string $entityType, string $eventName, string $status, ?string $referenceId, array|string $payload = [], array|string $response = [], string $direction = 'inbound'): void
    {
        $this->db->prepare('INSERT INTO tiktok_sync_logs (branch_id, entity_type, direction, event_name, status, reference_id, payload_preview, response_preview) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$branchId, $entityType, $direction, $eventName, $status, $referenceId, $this->encodePreview($payload), $this->encodePreview($response)]);
    }

    private function encodePreview(array|string $value): ?string
    {
        if ($value === '' || $value === []) {
            return null;
        }
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
