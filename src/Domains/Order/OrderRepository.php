<?php

declare(strict_types=1);

namespace KopiBot\Domains\Order;

use KopiBot\Core\DatabaseConnection;
use PDO;

class OrderRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DatabaseConnection::getInstance();
    }

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO orders (tenant_id, branch_id, customer_id, order_no, status, channel, subtotal, discount_total, grand_total, created_at) VALUES (:tenant_id, :branch_id, :customer_id, :order_no, :status, :channel, :subtotal, :discount_total, :grand_total, NOW())'
        );

        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function createItem(int $tenantId, int $branchId, int $orderId, OrderItemDTO $item): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO order_items (tenant_id, branch_id, order_id, product_id, product_name, qty, price, subtotal, created_at) VALUES (:tenant_id, :branch_id, :order_id, :product_id, :product_name, :qty, :price, :subtotal, NOW())'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'order_id' => $orderId,
            'product_id' => $item->productId,
            'product_name' => $item->productName,
            'qty' => $item->qty,
            'price' => $item->price,
            'subtotal' => $item->subtotal(),
        ]);
    }

    public function findById(int $tenantId, int $orderId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $orderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByOrderNumber(string $orderNumber): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE order_no = :order_no OR order_number = :order_number LIMIT 1');
        $stmt->execute([
            'order_no' => $orderNumber,
            'order_number' => $orderNumber,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findRecentByCustomer(int $customerId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM orders WHERE customer_id = :customer_id ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function findItems(int $tenantId, int $orderId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM order_items WHERE tenant_id = :tenant_id AND order_id = :order_id ORDER BY id ASC');
        $stmt->execute(['tenant_id' => $tenantId, 'order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    public function updateStatus(int $tenantId, int $orderId, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE tenant_id = :tenant_id AND id = :id');

        return $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $orderId,
            'status' => $status,
        ]);
    }

    public function updatePaymentStatus(int $orderId, string $paymentStatus): bool
    {
        $stmt = $this->db->prepare('UPDATE orders SET payment_status = :payment_status, updated_at = NOW() WHERE id = :id');

        return $stmt->execute([
            'id' => $orderId,
            'payment_status' => $paymentStatus,
        ]);
    }
}
