<?php

declare(strict_types=1);

use App\Config\Database;

final class TikTokOrderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function saveExternalOrder(array $mappedOrder): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tiktok_orders (branch_id,tiktok_order_id,customer_name,total_amount,order_status,payload_json)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE customer_name=VALUES(customer_name),total_amount=VALUES(total_amount),order_status=VALUES(order_status),payload_json=VALUES(payload_json)'
        );

        return $stmt->execute([
            (int)$mappedOrder['branch_id'],
            (string)$mappedOrder['external_order_id'],
            (string)($mappedOrder['customer']['name'] ?? ''),
            (float)($mappedOrder['order']['total_amount'] ?? 0),
            (string)($mappedOrder['order']['status'] ?? 'pending'),
            json_encode($mappedOrder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
