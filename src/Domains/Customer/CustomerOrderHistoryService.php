<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

use KopiBot\Core\Database;
use PDO;

class CustomerOrderHistoryService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function lastOrder(int $tenantId, int $customerId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE tenant_id = :tenant_id AND customer_id = :customer_id ORDER BY id DESC LIMIT 1');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
        ]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $order['items'] = $this->items($tenantId, (int) $order['id']);

        return $order;
    }

    public function items(int $tenantId, int $orderId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM order_items WHERE tenant_id = :tenant_id AND order_id = :order_id ORDER BY id ASC');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
        ]);

        return $stmt->fetchAll();
    }
}
