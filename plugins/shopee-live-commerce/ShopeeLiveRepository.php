<?php

declare(strict_types=1);

use App\Config\Database;

final class ShopeeLiveRepository
{
    public const PLUGIN_SLUG = 'shopee-live-commerce';
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function ensureSchema(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS shopee_sync_logs (
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
            INDEX idx_shopee_sync_branch_created (branch_id, created_at),
            INDEX idx_shopee_sync_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->db->exec('CREATE TABLE IF NOT EXISTS shopee_orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT UNSIGNED NOT NULL DEFAULT 0,
            shopee_order_sn VARCHAR(120) NOT NULL,
            customer_name VARCHAR(180) DEFAULT NULL,
            total_amount DECIMAL(12,2) DEFAULT 0,
            order_status VARCHAR(60) DEFAULT NULL,
            payload_json MEDIUMTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_shopee_order (branch_id, shopee_order_sn)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->db->exec('CREATE TABLE IF NOT EXISTS shopee_live_metrics (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT UNSIGNED NOT NULL,
            live_id VARCHAR(120) NOT NULL,
            viewers INT DEFAULT 0,
            comments_count INT DEFAULT 0,
            orders_count INT DEFAULT 0,
            revenue_amount DECIMAL(12,2) DEFAULT 0,
            payload_json MEDIUMTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_shopee_live(branch_id,live_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public function saveOrder(int $branchId, array $payload): void
    {
        $orderSn = (string)($payload['order_sn'] ?? $payload['ordersn'] ?? $payload['order_id'] ?? 'unknown-' . time());
        $this->db->prepare('INSERT INTO shopee_orders (branch_id, shopee_order_sn, customer_name, total_amount, order_status, payload_json)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE customer_name=VALUES(customer_name), total_amount=VALUES(total_amount), order_status=VALUES(order_status), payload_json=VALUES(payload_json)')
            ->execute([$branchId, $orderSn, (string)($payload['customer_name'] ?? ''), (float)($payload['total_amount'] ?? 0), (string)($payload['status'] ?? 'pending'), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    public function saveLiveMetric(int $branchId, array $payload): void
    {
        $this->db->prepare('INSERT INTO shopee_live_metrics (branch_id, live_id, viewers, comments_count, orders_count, revenue_amount, payload_json) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$branchId, (string)($payload['live_id'] ?? 'unknown'), (int)($payload['viewers'] ?? 0), (int)($payload['comments'] ?? 0), (int)($payload['orders'] ?? 0), (float)($payload['revenue'] ?? 0), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    public function logSync(int $branchId, string $entity, string $event, string $status, ?string $referenceId, array|string $payload = [], array|string $response = [], string $direction = 'inbound'): void
    {
        $encode = fn($v) => is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->db->prepare('INSERT INTO shopee_sync_logs (branch_id, entity_type, direction, event_name, status, reference_id, payload_preview, response_preview) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$branchId, $entity, $direction, $event, $status, $referenceId, $encode($payload), $encode($response)]);
    }
}
