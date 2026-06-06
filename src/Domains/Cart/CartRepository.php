<?php

declare(strict_types=1);

namespace KopiBot\Domains\Cart;

use KopiBot\Core\DatabaseConnection;
use PDO;

class CartRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DatabaseConnection::getInstance();
    }

    public function findOrCreateCart(int $tenantId, int $branchId, ?int $customerId, string $sessionId): int
    {
        $stmt = $this->db->prepare('SELECT id FROM carts WHERE tenant_id = :tenant_id AND branch_id = :branch_id AND session_id = :session_id AND status = :status LIMIT 1');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'session_id' => $sessionId,
            'status' => CartStatus::ACTIVE,
        ]);

        $cartId = $stmt->fetchColumn();

        if ($cartId) {
            return (int) $cartId;
        }

        $stmt = $this->db->prepare('INSERT INTO carts (tenant_id, branch_id, customer_id, session_id, status, created_at) VALUES (:tenant_id, :branch_id, :customer_id, :session_id, :status, NOW())');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'session_id' => $sessionId,
            'status' => CartStatus::ACTIVE,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function addItem(int $tenantId, int $branchId, int $cartId, CartItemDTO $item): int
    {
        $stmt = $this->db->prepare('INSERT INTO cart_items (tenant_id, branch_id, cart_id, product_id, product_name, qty, price, subtotal, created_at) VALUES (:tenant_id, :branch_id, :cart_id, :product_id, :product_name, :qty, :price, :subtotal, NOW())');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'cart_id' => $cartId,
            'product_id' => $item->productId,
            'product_name' => $item->productName,
            'qty' => $item->qty,
            'price' => $item->price,
            'subtotal' => $item->subtotal(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getItems(int $tenantId, int $cartId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM cart_items WHERE tenant_id = :tenant_id AND cart_id = :cart_id ORDER BY id ASC');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'cart_id' => $cartId,
        ]);

        return $stmt->fetchAll();
    }

    public function getItemsByCartId(int $cartId): array
    {
        if ($cartId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT
                ci.*,
                COALESCE(ci.name, ci.menu_name, ci.product_name, ci.product_name, p.name, m.name, "Item") AS name,
                COALESCE(ci.unit_price, ci.price, p.price, m.price, 0) AS unit_price,
                COALESCE(ci.quantity, ci.qty, 1) AS quantity
             FROM cart_items ci
             LEFT JOIN products p ON p.id = ci.product_id
             LEFT JOIN menu_items m ON m.id = ci.menu_item_id
             WHERE ci.cart_id = ?
             ORDER BY ci.id ASC'
        );
        $stmt->execute([$cartId]);

        return $stmt->fetchAll();
    }

    public function getBySession(string $sessionKey): ?array
    {
        if ($sessionKey === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM carts WHERE session_key = ? OR session_id = ? LIMIT 1');
        $stmt->execute([$sessionKey, $sessionKey]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markCheckedOut(int $tenantId, int $cartId, int $orderId): bool
    {
        $stmt = $this->db->prepare('UPDATE carts SET status = :status, order_id = :order_id, updated_at = NOW() WHERE tenant_id = :tenant_id AND id = :id');

        return $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $cartId,
            'status' => CartStatus::CHECKED_OUT,
            'order_id' => $orderId,
        ]);
    }
}
